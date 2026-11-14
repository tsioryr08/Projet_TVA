<?php

namespace App\Libraries\Parametrage;

use App\Libraries\Regles\RuleEngine;
use App\Libraries\Score\CalculScore;
use App\Libraries\Workflow\AuditService;
use Config\Database;

/**
 * Paramétrage des règles de contrôle depuis l'interface, sans toucher au code.
 *
 * Modifiable : poids, gravité, activation, et les seuils numériques ou booléens déjà présents dans
 * regles_controle.parametres (tolérances, seuils). Les codes (taux, comptes) ne sont pas modifiables ici.
 *
 * Garde-fous : rôle responsable_fiscal ou admin, motif obligatoire, valeurs bornées, trace dans le journal d'audit,
 * et désactiver ou déclasser une règle bloquante réservé à l'admin.
 * Chaque changement recalcule les anomalies et le score de la période : on voit l'effet immédiatement.
 * previsualiser() fait la même chose puis ANNULE tout (aucune donnée n'est modifiée).
 */
class ParametrageRegles
{
    public const GRAVITES = ['info', 'moyenne', 'elevee', 'bloquante'];
    public const POIDS_MAX = 10.0;

    /** @return list<array<string,mixed>> */
    public function lister(): array
    {
        $rows = Database::connect()->table('regles_controle')->orderBy('code')->get()->getResultArray();

        return array_map(fn ($r) => [
            'id' => (int) $r['id'], 'code' => $r['code'], 'intitule' => $r['intitule'], 'gravite' => $r['gravite'],
            'poids' => (float) $r['poids'], 'active' => (bool) $r['active'],
            'parametres' => json_decode((string) $r['parametres'], true) ?: [], 'source_legale' => $r['source_legale'],
        ], $rows);
    }

    /**
     * Simule une modification : renvoie son effet sur les anomalies et le score, sans rien enregistrer.
     *
     * @param array<string,mixed> $modifs ex. ['poids' => 3, 'active' => false, 'parametres' => ['tolerance_absolue' => 200]]
     *
     * @return array<string,mixed>
     */
    public function previsualiser(int $regleId, array $modifs, int $societeId, int $periodeId): array
    {
        $db = Database::connect();
        $db->transStart(true); // mode test : tout est annulé à transComplete()
        try {
            $res = $this->executer($regleId, $modifs, 'Prévisualisation', null, $societeId, $periodeId);
        } finally {
            $db->transComplete();
        }
        $res['applique'] = false;

        return $res;
    }

    /**
     * Applique une modification, journalise, puis recalcule anomalies et score de la période.
     *
     * @param array<string,mixed> $modifs
     *
     * @return array<string,mixed>
     */
    public function appliquer(int $regleId, array $modifs, string $motif, int $utilisateurId, int $societeId, int $periodeId): array
    {
        $user = Database::connect()->table('utilisateurs u')->select('u.id, r.code AS role_code')
            ->join('roles r', 'r.id = u.role_id')->where(['u.id' => $utilisateurId, 'u.actif' => 1])->get()->getRowArray();
        if ($user === null) {
            throw new ParametrageException('Utilisateur introuvable ou inactif.');
        }
        if (! in_array($user['role_code'], ['responsable_fiscal', 'admin'], true)) {
            throw new ParametrageException(sprintf('Le rôle « %s » n\'est pas autorisé à modifier les règles (rôle requis : responsable_fiscal ou admin).', $user['role_code']));
        }
        if (mb_strlen(trim($motif)) < 5) {
            throw new ParametrageException('Un motif est obligatoire (au moins 5 caractères) pour modifier une règle.');
        }

        $db = Database::connect();
        $db->transStart();
        try {
            $res = $this->executer($regleId, $modifs, trim($motif), $utilisateurId, $societeId, $periodeId, $user['role_code']);
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
        $db->transComplete();
        $res['applique'] = true;

        return $res;
    }

    /**
     * Cœur commun : mesure l'état avant, applique la modification, mesure l'état après.
     *
     * @param array<string,mixed> $modifs
     *
     * @return array<string,mixed>
     */
    private function executer(int $regleId, array $modifs, string $motif, ?int $utilisateurId, int $societeId, int $periodeId, ?string $role = null): array
    {
        $db    = Database::connect();
        $regle = $db->table('regles_controle')->where('id', $regleId)->get()->getRowArray()
            ?? throw new ParametrageException('Règle introuvable.');

        $changes = $this->valider($regle, $modifs);
        if ($changes['maj'] === []) {
            throw new ParametrageException('Aucune modification : les valeurs demandées sont identiques aux valeurs actuelles.');
        }

        // Garde-fou : une règle bloquante protège la validation. La désactiver ou la déclasser est réservé à l'admin.
        $affaiblit = (isset($changes['maj']['active']) && (int) $changes['maj']['active'] === 0) || isset($changes['maj']['gravite']);
        if ($role !== null && $role !== 'admin' && $regle['gravite'] === 'bloquante' && $affaiblit) {
            throw new ParametrageException('Désactiver ou déclasser une règle bloquante est réservé à l\'administrateur.');
        }

        $moteur = new RuleEngine();
        $calcul = new CalculScore();

        $moteur->executer($societeId, $periodeId);
        $avant = $calcul->calculer($societeId, $periodeId, false);
        $nbAvant = $this->compter($societeId, $periodeId);

        $db->table('regles_controle')->where('id', $regleId)->update($changes['maj'] + ['updated_at' => date('Y-m-d H:i:s')]);

        if ($utilisateurId !== null) {
            (new AuditService())->journaliser($utilisateurId, 'modifier_regle', 'regles_controle', $regleId,
                $changes['avant'], $changes['apres'] + ['motif' => $motif, 'regle' => $regle['code']]);
        }

        $moteur->executer($societeId, $periodeId);
        $apres = $calcul->calculer($societeId, $periodeId, $utilisateurId !== null);
        $nbApres = $this->compter($societeId, $periodeId);

        return [
            'regle'         => $regle['code'],
            'modifications' => $changes['affichage'],
            'score_avant'   => $avant['score'], 'niveau_avant' => $avant['niveau'],
            'score_apres'   => $apres['score'], 'niveau_apres' => $apres['niveau'],
            'variation'     => round($apres['score'] - $avant['score'], 1),
            'anomalies_avant' => $nbAvant['total'], 'anomalies_apres' => $nbApres['total'],
            'bloquantes_avant' => $nbAvant['bloquantes'], 'bloquantes_apres' => $nbApres['bloquantes'],
            'validation_bloquee_apres' => $nbApres['bloquantes'] > 0,
        ];
    }

    /**
     * Contrôle et normalise les modifications demandées.
     *
     * @param array<string,mixed> $regle
     * @param array<string,mixed> $modifs
     *
     * @return array{maj:array<string,mixed>,avant:array<string,mixed>,apres:array<string,mixed>,affichage:array<string,mixed>}
     */
    private function valider(array $regle, array $modifs): array
    {
        $inconnus = array_diff(array_keys($modifs), ['poids', 'gravite', 'active', 'parametres']);
        if ($inconnus !== []) {
            throw new ParametrageException('Champ non modifiable : ' . implode(', ', $inconnus) . '.');
        }

        $maj = $avant = $apres = $affichage = [];

        if (array_key_exists('poids', $modifs)) {
            if (! is_numeric($modifs['poids']) || (float) $modifs['poids'] < 0 || (float) $modifs['poids'] > self::POIDS_MAX) {
                throw new ParametrageException(sprintf('Le poids doit être un nombre entre 0 et %s.', self::POIDS_MAX));
            }
            $nouveau = round((float) $modifs['poids'], 2);
            if ($nouveau !== round((float) $regle['poids'], 2)) {
                $maj['poids'] = $nouveau;
                $avant['poids'] = number_format((float) $regle['poids'], 2, '.', '');
                $apres['poids'] = number_format($nouveau, 2, '.', '');
                $affichage['poids'] = ['avant' => (float) $regle['poids'], 'apres' => $nouveau];
            }
        }

        if (array_key_exists('gravite', $modifs)) {
            if (! in_array($modifs['gravite'], self::GRAVITES, true)) {
                throw new ParametrageException('Gravité invalide (valeurs possibles : ' . implode(', ', self::GRAVITES) . ').');
            }
            if ($modifs['gravite'] !== $regle['gravite']) {
                $maj['gravite'] = $modifs['gravite'];
                $avant['gravite'] = $regle['gravite'];
                $apres['gravite'] = $modifs['gravite'];
                $affichage['gravite'] = ['avant' => $regle['gravite'], 'apres' => $modifs['gravite']];
            }
        }

        if (array_key_exists('active', $modifs)) {
            $nouveau = filter_var($modifs['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($nouveau === null) {
                throw new ParametrageException('« active » doit être vrai ou faux.');
            }
            if ((int) $nouveau !== (int) $regle['active']) {
                $maj['active'] = (int) $nouveau;
                $avant['active'] = (int) $regle['active'];
                $apres['active'] = (int) $nouveau;
                $affichage['active'] = ['avant' => (bool) $regle['active'], 'apres' => $nouveau];
            }
        }

        if (array_key_exists('parametres', $modifs)) {
            if (! is_array($modifs['parametres'])) {
                throw new ParametrageException('« parametres » doit être un objet clé/valeur.');
            }
            $actuels = json_decode((string) $regle['parametres'], true) ?: [];
            $nouveaux = $actuels;
            foreach ($modifs['parametres'] as $cle => $valeur) {
                if (! array_key_exists($cle, $actuels)) {
                    throw new ParametrageException(sprintf('Paramètre inconnu pour %s : « %s ».', $regle['code'], $cle));
                }
                if (is_bool($actuels[$cle])) {
                    $v = filter_var($valeur, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($v === null) {
                        throw new ParametrageException(sprintf('« %s » doit être vrai ou faux.', $cle));
                    }
                } elseif (is_int($actuels[$cle]) || is_float($actuels[$cle])) {
                    if (! is_numeric($valeur) || (float) $valeur < 0) {
                        throw new ParametrageException(sprintf('« %s » doit être un nombre positif ou nul.', $cle));
                    }
                    $v = $valeur + 0;
                } else {
                    throw new ParametrageException(sprintf('« %s » n\'est pas modifiable ici (code technique).', $cle));
                }
                if ($v != $actuels[$cle]) { // comparaison souple : 100 et 100.0 sont identiques
                    $affichage['parametres'][$cle] = ['avant' => $actuels[$cle], 'apres' => $v];
                    $nouveaux[$cle] = $v;
                }
            }
            if ($nouveaux !== $actuels) {
                $maj['parametres'] = json_encode($nouveaux, JSON_UNESCAPED_UNICODE);
                $avant['parametres'] = json_encode($actuels, JSON_UNESCAPED_UNICODE);
                $apres['parametres'] = $maj['parametres'];
            }
        }

        return ['maj' => $maj, 'avant' => $avant, 'apres' => $apres, 'affichage' => $affichage];
    }

    /** @return array{total:int,bloquantes:int} */
    private function compter(int $societeId, int $periodeId): array
    {
        $b = Database::connect()->table('anomalies a')->join('regles_controle r', 'r.id = a.regle_id')
            ->where(['a.societe_id' => $societeId, 'a.periode_id' => $periodeId, 'r.active' => 1]);
        $total = (clone $b)->countAllResults();
        $bloq  = (clone $b)->where(['a.severite' => 'bloquante', 'a.statut' => 'ouverte'])->countAllResults();

        return ['total' => $total, 'bloquantes' => $bloq];
    }
}
