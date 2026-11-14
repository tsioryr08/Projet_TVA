<?php

namespace App\Libraries\Workflow;

use App\Libraries\Score\CalculScore;
use Config\Database;
use Throwable;

/**
 * Workflow de validation d'une déclaration.
 *
 *   brouillon --soumettre--> a_valider --valider--> validee --deposer--> deposee --archiver--> archivee
 *        ^                       |
 *        +---- a_corriger <------+  (renvoyer, commentaire obligatoire)
 *
 * Règles : rôle autorisé par action, statut de départ, blocage tant qu'une anomalie bloquante est ouverte
 * (soumettre et valider), séparation des tâches (qui a soumis ne peut pas valider), référence de virement
 * pour déposer. Chaque transition est enregistrée dans validations (étapes humaines) et dans le journal d'audit.
 */
class WorkflowService
{
    public const TRANSITIONS = [
        'soumettre' => ['de' => ['brouillon', 'a_corriger'], 'vers' => 'a_valider', 'roles' => ['comptable', 'admin'],
            'etape' => 'revue_comptable', 'decision' => 'valide', 'bloquee_par_anomalies' => true],
        'renvoyer'  => ['de' => ['a_valider'], 'vers' => 'a_corriger', 'roles' => ['responsable_fiscal', 'admin'],
            'etape' => 'validation_responsable', 'decision' => 'a_corriger', 'commentaire_requis' => true],
        'valider'   => ['de' => ['a_valider'], 'vers' => 'validee', 'roles' => ['responsable_fiscal', 'admin'],
            'etape' => 'validation_responsable', 'decision' => 'valide', 'bloquee_par_anomalies' => true],
        'deposer'   => ['de' => ['validee'], 'vers' => 'deposee', 'roles' => ['responsable_fiscal', 'admin'],
            'etape' => null, 'reference_requise' => true],
        'archiver'  => ['de' => ['deposee'], 'vers' => 'archivee', 'roles' => ['responsable_fiscal', 'admin'],
            'etape' => null],
    ];

    /**
     * Effectue une transition ou lève WorkflowException avec le motif du refus.
     *
     * @param array{reference_virement?:string} $extra
     *
     * @return array{action:string,de:string,vers:string,score:?string,niveau:?string}
     */
    public function transitionner(int $declarationId, string $action, int $utilisateurId, ?string $commentaire = null, array $extra = []): array
    {
        $t = self::TRANSITIONS[$action] ?? throw new WorkflowException('Action inconnue : ' . $action);
        $decl = $this->declaration($declarationId);
        $user = $this->utilisateur($utilisateurId);

        $erreur = $this->verifier($action, $t, $decl, $user, $commentaire, $extra, true);
        if ($erreur !== null) {
            throw new WorkflowException($erreur);
        }

        $score = null;
        if ($t['bloquee_par_anomalies'] ?? false) {
            $score = (new CalculScore())->calculer((int) $decl['societe_id'], (int) $decl['periode_id'], false);
        }

        $db = Database::connect();
        $db->transStart();
        try {
            $maj = ['statut' => $t['vers']];
            if ($action === 'deposer') {
                $maj['reference_virement'] = trim((string) $extra['reference_virement']);
                $maj['date_depot']         = date('Y-m-d');
            }
            $db->table('declarations')->where('id', $declarationId)->update($maj + ['updated_at' => date('Y-m-d H:i:s')]);

            if ($t['etape'] !== null) {
                $db->table('validations')->insert([
                    'declaration_id' => $declarationId, 'utilisateur_id' => $utilisateurId, 'etape' => $t['etape'],
                    'decision' => $t['decision'], 'commentaire' => $commentaire, 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $apres = ['statut' => $t['vers'], 'role' => $user['role_code']];
            if ($commentaire !== null && trim($commentaire) !== '') {
                $apres['commentaire'] = $commentaire;
            }
            if ($score !== null) {
                $apres['score'] = (string) $score['score'];
                $apres['niveau'] = $score['niveau'];
            }
            if (isset($maj['reference_virement'])) {
                $apres['reference_virement'] = $maj['reference_virement'];
            }
            (new AuditService())->journaliser($utilisateurId, $action, 'declarations', $declarationId, ['statut' => $decl['statut']], $apres);
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }
        $db->transComplete();

        return ['action' => $action, 'de' => $decl['statut'], 'vers' => $t['vers'], 'score' => $score ? (string) $score['score'] : null, 'niveau' => $score['niveau'] ?? null];
    }

    /**
     * Actions que cet utilisateur peut tenter sur cette déclaration, avec le motif si elle est indisponible.
     * Sert à afficher ou griser les boutons dans l'interface.
     *
     * @return list<array{action:string,vers:string,autorisee:bool,raison:?string}>
     */
    public function actionsPossibles(int $declarationId, int $utilisateurId): array
    {
        $decl = $this->declaration($declarationId);
        $user = $this->utilisateur($utilisateurId);
        $out  = [];

        foreach (self::TRANSITIONS as $action => $t) {
            $raison = $this->verifier($action, $t, $decl, $user, null, [], false);
            $out[]  = ['action' => $action, 'vers' => $t['vers'], 'autorisee' => $raison === null, 'raison' => $raison];
        }

        return $out;
    }

    /** Nombre d'anomalies bloquantes encore ouvertes pour la société et la période de la déclaration. */
    public function anomaliesBloquantes(array $declaration): int
    {
        return Database::connect()->table('anomalies a')->join('regles_controle r', 'r.id = a.regle_id')->where([
            'a.societe_id' => $declaration['societe_id'], 'a.periode_id' => $declaration['periode_id'],
            'a.severite' => 'bloquante', 'a.statut' => 'ouverte', 'r.active' => 1,
        ])->countAllResults();
    }

    /**
     * @param array<string,mixed> $t
     * @param array<string,mixed> $decl
     * @param array<string,mixed> $user
     * @param array<string,mixed> $extra
     */
    private function verifier(string $action, array $t, array $decl, array $user, ?string $commentaire, array $extra, bool $strict): ?string
    {
        if (! in_array($decl['statut'], $t['de'], true)) {
            return sprintf('Action « %s » impossible : la déclaration est « %s » (statut requis : %s).', $action, $decl['statut'], implode(' ou ', $t['de']));
        }
        if (! in_array($user['role_code'], $t['roles'], true)) {
            return sprintf('Le rôle « %s » n\'est pas autorisé à effectuer « %s » (rôle requis : %s).', $user['role_code'], $action, implode(' ou ', $t['roles']));
        }
        if (($t['bloquee_par_anomalies'] ?? false) && ($n = $this->anomaliesBloquantes($decl)) > 0) {
            return sprintf('%d anomalie(s) bloquante(s) ouverte(s) : corrigez-les ou justifiez-les avant de continuer.', $n);
        }
        if ($action === 'valider') {
            $revue = Database::connect()->table('validations')
                ->where(['declaration_id' => $decl['id'], 'etape' => 'revue_comptable'])->orderBy('id', 'DESC')->get(1)->getRowArray();
            if ($revue !== null && (int) $revue['utilisateur_id'] === (int) $user['id']) {
                return 'Séparation des tâches : la personne qui a soumis la déclaration ne peut pas la valider.';
            }
        }
        if ($strict && ($t['commentaire_requis'] ?? false) && trim((string) $commentaire) === '') {
            return 'Un commentaire est obligatoire pour cette action.';
        }
        if ($strict && ($t['reference_requise'] ?? false) && trim((string) ($extra['reference_virement'] ?? '')) === '') {
            return 'La référence du virement est obligatoire pour déposer la déclaration.';
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function declaration(int $id): array
    {
        return Database::connect()->table('declarations')->where('id', $id)->get()->getRowArray()
            ?? throw new WorkflowException('Déclaration introuvable.');
    }

    /** @return array<string,mixed> */
    private function utilisateur(int $id): array
    {
        return Database::connect()->table('utilisateurs u')->select('u.id, u.nom, u.email, r.code AS role_code')
            ->join('roles r', 'r.id = u.role_id')->where(['u.id' => $id, 'u.actif' => 1])->get()->getRowArray()
            ?? throw new WorkflowException('Utilisateur introuvable ou inactif.');
    }
}
