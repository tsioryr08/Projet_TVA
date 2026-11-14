<?php

namespace App\Libraries\Anomalies;

use App\Libraries\Regles\RuleEngine;
use App\Libraries\Score\CalculScore;
use App\Libraries\Workflow\AuditService;
use Config\Database;

/**
 * Consultation et traitement des anomalies : liste filtrée, détail (preuves et historique), et quatre actions.
 *
 *   justifier : ouverte -> justifiee   commentaire obligatoire ; une anomalie BLOQUANTE ne peut être justifiée que
 *                                      par un responsable fiscal ou un admin (sinon on lèverait un blocage sans correction)
 *   corriger  : ouverte|justifiee -> corrigee   refusée si l'anomalie est TOUJOURS détectée après vérification des données
 *   ignorer   : ouverte|justifiee -> ignoree    responsable fiscal ou admin, commentaire obligatoire
 *   rouvrir   : justifiee|corrigee|ignoree -> ouverte   responsable fiscal ou admin, commentaire obligatoire
 *
 * Chaque action est inscrite dans le journal d'audit (avec le score du moment) puis le score est recalculé :
 * on voit tout de suite l'effet. Le blocage de la workflow tient compte du nouveau statut.
 */
class AnomalieService
{
    public const MIN_COMMENTAIRE = 10;

    public const ACTIONS = [
        'justifier' => ['de' => ['ouverte'], 'vers' => 'justifiee', 'roles' => ['comptable', 'responsable_fiscal', 'admin'], 'commentaire_requis' => true],
        'corriger'  => ['de' => ['ouverte', 'justifiee'], 'vers' => 'corrigee', 'roles' => ['comptable', 'responsable_fiscal', 'admin'], 'commentaire_requis' => false, 'verifier_donnees' => true],
        'ignorer'   => ['de' => ['ouverte', 'justifiee'], 'vers' => 'ignoree', 'roles' => ['responsable_fiscal', 'admin'], 'commentaire_requis' => true],
        'rouvrir'   => ['de' => ['justifiee', 'corrigee', 'ignoree'], 'vers' => 'ouverte', 'roles' => ['responsable_fiscal', 'admin'], 'commentaire_requis' => true],
    ];

    /**
     * Anomalies d'une période (règles actives), les plus graves et les plus grosses d'abord.
     *
     * @param array{regle?:string,severite?:string,statut?:string} $filtres
     *
     * @return list<array<string,mixed>>
     */
    public function lister(int $societeId, int $periodeId, array $filtres = []): array
    {
        $b = Database::connect()->table('anomalies a')
            ->select('a.id, a.severite, a.statut, a.montant_concerne, a.message, a.action_corrective, a.reference_type, a.reference_id, a.justification, a.detectee_le, a.traitee_par, r.code AS regle_code, r.intitule AS regle_intitule')
            ->join('regles_controle r', 'r.id = a.regle_id')
            ->where(['a.societe_id' => $societeId, 'a.periode_id' => $periodeId, 'r.active' => 1]);

        foreach (['regle' => 'r.code', 'severite' => 'a.severite', 'statut' => 'a.statut'] as $cle => $colonne) {
            if (! empty($filtres[$cle])) {
                $b->where($colonne, $filtres[$cle]);
            }
        }

        $rows = $b->orderBy("FIELD(a.severite, 'bloquante', 'elevee', 'moyenne', 'info')", '', false)->orderBy('a.montant_concerne', 'DESC')->get()->getResultArray();

        return array_map(function ($r) {
            $r['id'] = (int) $r['id'];
            $r['montant_concerne'] = (float) $r['montant_concerne'];

            return $r;
        }, $rows);
    }

    /**
     * Fiche complète : anomalie, règle, dossier de preuve et historique des traitements.
     *
     * @return array<string,mixed>
     */
    public function detail(int $anomalieId): array
    {
        $db = Database::connect();
        $a  = $db->table('anomalies a')
            ->select('a.*, r.code AS regle_code, r.intitule AS regle_intitule, r.description AS regle_description, r.source_legale, r.message_erreur, r.poids, r.active AS regle_active')
            ->join('regles_controle r', 'r.id = a.regle_id')->where('a.id', $anomalieId)->get()->getRowArray()
            ?? throw new AnomalieException('Anomalie introuvable.');

        $preuves = $db->table('anomalie_preuves')->where('anomalie_id', $anomalieId)->orderBy('id')->get()->getResultArray();

        $histo = $db->table('journal_audit j')->select('j.created_at, j.action, j.donnees_avant, j.donnees_apres, u.nom')
            ->join('utilisateurs u', 'u.id = j.utilisateur_id', 'left')
            ->where(['j.table_cible' => 'anomalies', 'j.enregistrement_id' => $anomalieId])->orderBy('j.id')->get()->getResultArray();
        $historique = array_map(function ($h) {
            $apres = json_decode((string) $h['donnees_apres'], true) ?: [];
            $avant = json_decode((string) $h['donnees_avant'], true) ?: [];

            return ['date' => $h['created_at'], 'utilisateur' => $h['nom'], 'action' => str_replace('anomalie_', '', $h['action']),
                'de' => $avant['statut'] ?? null, 'vers' => $apres['statut'] ?? null, 'commentaire' => $apres['commentaire'] ?? null];
        }, $histo);

        return ['anomalie' => $a, 'preuves' => $preuves, 'historique' => $historique];
    }

    /**
     * Actions que cet utilisateur peut tenter sur cette anomalie, avec le motif si elle est indisponible.
     *
     * @return list<array{action:string,vers:string,autorisee:bool,raison:?string}>
     */
    public function actionsPossibles(int $anomalieId, int $utilisateurId): array
    {
        $a = $this->anomalie($anomalieId);
        $u = $this->utilisateur($utilisateurId);
        $out = [];
        foreach (self::ACTIONS as $action => $def) {
            $raison = $this->verifier($action, $def, $a, $u, null, false);
            $out[]  = ['action' => $action, 'vers' => $def['vers'], 'autorisee' => $raison === null, 'raison' => $raison];
        }

        return $out;
    }

    /**
     * Applique une action, journalise, puis recalcule le score.
     *
     * @return array<string,mixed>
     */
    public function traiter(int $anomalieId, string $action, int $utilisateurId, ?string $commentaire = null): array
    {
        $def = self::ACTIONS[$action] ?? throw new AnomalieException('Action inconnue : ' . $action . ' (actions : ' . implode(', ', array_keys(self::ACTIONS)) . ').');
        $a   = $this->anomalie($anomalieId);
        $u   = $this->utilisateur($utilisateurId);

        if (($erreur = $this->verifier($action, $def, $a, $u, $commentaire, true)) !== null) {
            throw new AnomalieException($erreur);
        }

        $societeId = (int) $a['societe_id'];
        $periodeId = (int) $a['periode_id'];
        $calcul    = new CalculScore();
        $avant     = $calcul->calculer($societeId, $periodeId, false);
        $commentaire = $commentaire !== null ? trim($commentaire) : null;

        $db = Database::connect();
        $db->transStart();
        try {
            $db->table('anomalies')->where('id', $anomalieId)->update([
                'statut' => $def['vers'], 'justification' => $commentaire !== '' ? $commentaire : null,
                'traitee_par' => $utilisateurId, 'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $apres = ['statut' => $def['vers'], 'regle' => $a['regle_code'], 'role' => $u['role_code'], 'score_avant' => (string) $avant['score']];
            if ($commentaire !== null && $commentaire !== '') {
                $apres['commentaire'] = $commentaire;
            }
            (new AuditService())->journaliser($utilisateurId, 'anomalie_' . $action, 'anomalies', $anomalieId, ['statut' => $a['statut']], $apres);
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
        $db->transComplete();

        $apresScore = $calcul->calculer($societeId, $periodeId, true);
        $bloquantes = $db->table('anomalies an')->join('regles_controle r', 'r.id = an.regle_id')
            ->where(['an.societe_id' => $societeId, 'an.periode_id' => $periodeId, 'an.severite' => 'bloquante', 'an.statut' => 'ouverte', 'r.active' => 1])->countAllResults();

        return [
            'anomalie_id' => $anomalieId, 'regle' => $a['regle_code'], 'action' => $action, 'de' => $a['statut'], 'vers' => $def['vers'],
            'score_avant' => $avant['score'], 'niveau_avant' => $avant['niveau'],
            'score_apres' => $apresScore['score'], 'niveau_apres' => $apresScore['niveau'], 'variation' => round($apresScore['score'] - $avant['score'], 1),
            'bloquantes_ouvertes' => $bloquantes, 'validation_bloquee' => $bloquantes > 0,
        ];
    }

    /**
     * @param array<string,mixed> $def
     * @param array<string,mixed> $a
     * @param array<string,mixed> $u
     */
    private function verifier(string $action, array $def, array $a, array $u, ?string $commentaire, bool $strict): ?string
    {
        if (! (int) $a['regle_active']) {
            return 'La règle ' . $a['regle_code'] . ' est désactivée : ses anomalies ne sont plus traitées.';
        }
        if (! in_array($a['statut'], $def['de'], true)) {
            return sprintf('Action « %s » impossible : l\'anomalie est « %s » (statut requis : %s).', $action, $a['statut'], implode(' ou ', $def['de']));
        }
        if (! in_array($u['role_code'], $def['roles'], true)) {
            return sprintf('Le rôle « %s » n\'est pas autorisé à « %s » (rôle requis : %s).', $u['role_code'], $action, implode(' ou ', $def['roles']));
        }
        if ($action === 'justifier' && $a['severite'] === 'bloquante' && ! in_array($u['role_code'], ['responsable_fiscal', 'admin'], true)) {
            return 'Une anomalie bloquante ne peut être justifiée que par un responsable fiscal ou un administrateur : corrigez les données, ou demandez-lui.';
        }
        if ($strict && ($def['commentaire_requis'] ?? false) && mb_strlen(trim((string) $commentaire)) < self::MIN_COMMENTAIRE) {
            return sprintf('Un commentaire de %d caractères minimum est obligatoire pour « %s ».', self::MIN_COMMENTAIRE, $action);
        }
        if ($strict && ($def['verifier_donnees'] ?? false) && $this->encoreDetectee($a)) {
            return 'L\'anomalie est toujours détectée sur les données actuelles : corrigez l\'écriture ou le bulletin concerné avant de la marquer corrigée.';
        }

        return null;
    }

    /**
     * Rejoue le moteur dans une transaction annulée, en retirant l'anomalie : si la même règle la détecte encore
     * sur la même référence, les données n'ont pas été corrigées. Rien n'est enregistré.
     *
     * @param array<string,mixed> $a
     */
    private function encoreDetectee(array $a): bool
    {
        $db = Database::connect();
        $db->transStart(true);
        try {
            $db->table('anomalies')->where('id', $a['id'])->delete();
            (new RuleEngine())->executer((int) $a['societe_id'], (int) $a['periode_id']);

            $q = $db->table('anomalies')->where([
                'societe_id' => $a['societe_id'], 'periode_id' => $a['periode_id'], 'regle_id' => $a['regle_id'],
                'reference_type' => $a['reference_type'], 'statut' => 'ouverte',
            ]);
            $a['reference_id'] === null ? $q->where('reference_id IS NULL', null, false) : $q->where('reference_id', $a['reference_id']);
            $encore = $q->countAllResults() > 0;
        } finally {
            $db->transComplete(); // mode test : tout est annulé
        }

        return $encore;
    }

    /** @return array<string,mixed> */
    private function anomalie(int $id): array
    {
        return Database::connect()->table('anomalies a')->select('a.*, r.code AS regle_code, r.active AS regle_active')
            ->join('regles_controle r', 'r.id = a.regle_id')->where('a.id', $id)->get()->getRowArray()
            ?? throw new AnomalieException('Anomalie introuvable.');
    }

    /** @return array<string,mixed> */
    private function utilisateur(int $id): array
    {
        return Database::connect()->table('utilisateurs u')->select('u.id, u.nom, r.code AS role_code')
            ->join('roles r', 'r.id = u.role_id')->where(['u.id' => $id, 'u.actif' => 1])->get()->getRowArray()
            ?? throw new AnomalieException('Utilisateur introuvable ou inactif.');
    }
}
