<?php

namespace App\Libraries\Score;

use App\Models\ScoreRisqueModel;
use Config\Database;

/**
 * Calcule le score de risque d'une société sur une période, à partir de la table anomalies.
 * Le score est EXPLIQUÉ : le détail par règle (« pourquoi 85/100 ? ») est enregistré avec lui.
 *
 * Les paramètres (multiplicateur, points, coefficients, seuils) sont dans app/Config/Score.php.
 */
class CalculScore
{
    /**
     * @return array{score:float,niveau:string,facteur_historique:float,detail:array<string,mixed>,id:?int}
     */
    public function calculer(int $societeId, int $periodeId, bool $enregistrer = true): array
    {
        /** @var \Config\Score $cfg */
        $cfg = config('Score');
        $db  = Database::connect();

        $anomalies = $db->table('anomalies a')
            ->select('a.id, a.severite, a.statut, a.montant_concerne, r.code AS regle_code, r.intitule, r.poids')
            ->join('regles_controle r', 'r.id = a.regle_id')
            ->where(['a.societe_id' => $societeId, 'a.periode_id' => $periodeId])
            ->orderBy('r.code')->orderBy('a.id')
            ->get()->getResultArray();

        // --- Points par règle -------------------------------------------------------------------
        $regles = [];
        $bloquantes = 0;
        foreach ($anomalies as $a) {
            $points = ($cfg->points[$a['severite']] ?? 0) * (float) $a['poids'] * ($cfg->coefficientStatut[$a['statut']] ?? 0);

            $code = $a['regle_code'];
            $regles[$code] ??= ['code' => $code, 'intitule' => $a['intitule'], 'poids' => (float) $a['poids'], 'nb_anomalies' => 0, 'points' => 0.0];
            $regles[$code]['nb_anomalies']++;
            $regles[$code]['points'] += $points;

            if ($a['severite'] === 'bloquante' && $a['statut'] === 'ouverte') {
                $bloquantes++;
            }
        }

        $somme = 0.0;
        foreach ($regles as &$r) {
            $r['contribution'] = round($r['points'] * $cfg->multiplicateur, 2);
            $somme += $r['points'];
        }
        unset($r);

        // --- Facteur historique : redressement passé sur une règle qui se déclenche aujourd'hui --
        $declencheurs = [];
        $historique = $db->table('controles_fiscaux_historique')->where('societe_id', $societeId)->get()->getResultArray();
        foreach ($historique as $h) {
            $code = $h['regle_code'];
            if ($code !== null && isset($regles[$code]) && $regles[$code]['points'] > 0) {
                $declencheurs[] = [
                    'regle'              => $code,
                    'date_controle'      => $h['date_controle'],
                    'type_impot'         => $h['type_impot'],
                    'redressement_montant' => (float) $h['redressement_montant'],
                    'motif'              => $h['motif'],
                ];
            }
        }
        $majoration = $declencheurs === [] ? 0.0 : $cfg->majorationHistorique;

        // --- Score et niveau ------------------------------------------------------------------
        $brut  = round($cfg->multiplicateur * $somme + $majoration, 2);
        $score = round(min(100.0, max(0.0, $brut)), 1);

        $niveau = match (true) {
            $score >= $cfg->seuilCritique => 'critique',
            $score >= $cfg->seuilEleve    => 'eleve',
            $score >= $cfg->seuilMoyen    => 'moyen',
            default                       => 'faible',
        };

        $detail = [
            'multiplicateur'                => $cfg->multiplicateur,
            'somme_points'                  => round($somme, 2),
            'regles'                        => array_values($regles),
            'historique'                    => ['majoration' => $majoration, 'declencheurs' => $declencheurs],
            'score_brut'                    => $brut,
            'plafonne'                      => $brut > 100,
            'anomalies_bloquantes_ouvertes' => $bloquantes,
            'nb_anomalies'                  => count($anomalies),
        ];

        $id = null;
        if ($enregistrer) {
            $id = (new ScoreRisqueModel())->insert([
                'societe_id'         => $societeId,
                'periode_id'         => $periodeId,
                'score'              => $score,
                'facteur_historique' => $majoration,
                'niveau'             => $niveau,
                'detail'             => json_encode($detail, JSON_UNESCAPED_UNICODE),
                'calcule_le'         => date('Y-m-d H:i:s'),
            ]);
        }

        return ['score' => $score, 'niveau' => $niveau, 'facteur_historique' => $majoration, 'detail' => $detail, 'id' => $id ?: null];
    }
}
