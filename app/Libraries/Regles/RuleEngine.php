<?php

namespace App\Libraries\Regles;

use App\Models\AnomalieModel;
use App\Models\AnomaliePreuveModel;
use App\Models\PeriodeFiscaleModel;
use App\Models\RegleControleModel;
use App\Models\SocieteModel;
use Config\Database;
use RuntimeException;

/**
 * Service d'exécution des règles : lit les règles actives en base, les exécute sur une période,
 * puis enregistre les anomalies et leurs preuves.
 *
 * Idempotent : relancer le moteur supprime les anomalies encore « ouvertes » de la période et les recrée.
 * Les anomalies déjà traitées (justifiée, corrigée, ignorée) sont conservées et ne sont pas recréées.
 */
class RuleEngine
{
    /** Code de règle => classe. Ajouter ici toute nouvelle règle. */
    public const REGLES = [
        'R1' => R1TvaCollectee::class,
        'R2' => R2TvaDeductible::class,
        'R3' => R3BaseIrsa::class,
        'R4' => R4IrsaRecalcule::class,
        'R5' => R5RetenueSource::class,
        'R6' => R6Periodes::class,
    ];

    /**
     * @return list<array{code:string,intitule:string,gravite:string,nb:int,montant:float}>
     */
    public function executer(int $societeId, int $periodeId): array
    {
        $societe = (new SocieteModel())->find($societeId);
        $periode = (new PeriodeFiscaleModel())->find($periodeId);

        if ($societe === null || $periode === null) {
            throw new RuntimeException('Société ou période introuvable.');
        }

        $ctx             = new ContexteControle(Database::connect(), $societe, $periode);
        $anomalieModel   = new AnomalieModel();
        $preuveModel     = new AnomaliePreuveModel();
        $maintenant      = date('Y-m-d H:i:s');

        // Les anomalies déjà traitées sont conservées : on retient leur clé pour ne pas les recréer.
        $dejaTraitees = [];
        foreach ($anomalieModel->where(['societe_id' => $societeId, 'periode_id' => $periodeId])->where('statut !=', 'ouverte')->findAll() as $a) {
            $dejaTraitees[$this->cle((int) $a['regle_id'], $a['reference_type'], $a['reference_id'], (string) $a['message'])] = true;
        }

        // Les anomalies ouvertes sont recalculées (leurs preuves disparaissent par cascade).
        $anomalieModel->where(['societe_id' => $societeId, 'periode_id' => $periodeId, 'statut' => 'ouverte'])->delete();

        $resume = [];
        foreach ((new RegleControleModel())->where('active', 1)->orderBy('code')->findAll() as $regle) {
            $classe = self::REGLES[$regle['code']] ?? null;
            if ($classe === null) {
                continue; // règle en base sans classe : ignorée
            }

            /** @var RegleControle $instance */
            $instance = new $classe();
            $params   = json_decode((string) $regle['parametres'], true) ?: [];
            $nb       = 0;
            $montant  = 0.0;

            foreach ($instance->executer($ctx, $params) as $res) {
                if (isset($dejaTraitees[$this->cle((int) $regle['id'], $res->referenceType, $res->referenceId, $res->message)])) {
                    continue;
                }

                $anomalieId = $anomalieModel->insert([
                    'societe_id'        => $societeId,
                    'periode_id'        => $periodeId,
                    'regle_id'          => $regle['id'],
                    'severite'          => $regle['gravite'],
                    'montant_concerne'  => $res->montantConcerne,
                    'reference_type'    => $res->referenceType,
                    'reference_id'      => $res->referenceId,
                    'message'           => $res->message,
                    'action_corrective' => $regle['action_corrective'],
                    'statut'            => 'ouverte',
                    'detectee_le'       => $maintenant,
                ]);

                foreach ($res->preuves as $p) {
                    $preuveModel->insert([
                        'anomalie_id'       => $anomalieId,
                        'table_source'      => $p['table'],
                        'enregistrement_id' => $p['id'],
                        'libelle'           => $p['libelle'],
                        'montant'           => $p['montant'],
                    ]);
                }

                $nb++;
                $montant += $res->montantConcerne;
            }

            $resume[] = ['code' => $regle['code'], 'intitule' => $regle['intitule'], 'gravite' => $regle['gravite'], 'nb' => $nb, 'montant' => $montant];
        }

        return $resume;
    }

    private function cle(int $regleId, ?string $refType, mixed $refId, string $message): string
    {
        return $regleId . '|' . $refType . '|' . $refId . '|' . $message;
    }
}
