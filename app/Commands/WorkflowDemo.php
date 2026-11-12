<?php

namespace App\Commands;

use App\Libraries\Regles\RuleEngine;
use App\Libraries\Workflow\AuditService;
use App\Libraries\Workflow\DeclarationService;
use App\Libraries\Workflow\WorkflowException;
use App\Libraries\Workflow\WorkflowService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Scénario de démonstration du workflow, de bout en bout, sur la période 10/2026.
 * Il REMET À ZÉRO les anomalies et la déclaration TVA de la période avant de commencer (rejouable).
 * Usage : php spark workflow:demo
 */
class WorkflowDemo extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'workflow:demo';
    protected $description = 'Déroule le workflow complet (génération, blocage, correction, validation, dépôt, archivage).';
    protected $usage       = 'workflow:demo';

    public function run(array $params)
    {
        $db      = Database::connect();
        $societe = $db->table('societes')->get()->getRowArray();
        $periode = $db->table('periodes_fiscales')->where(['annee' => 2026, 'mois' => 10])->get()->getRowArray();
        $user    = fn (string $email) => (int) $db->table('utilisateurs')->where('email', $email)->get()->getRowArray()['id'];
        $comptable = $user('comptable@demo.mg');
        $resp      = $user('fiscal@demo.mg');

        // Remise à zéro de la période
        $db->table('declarations')->where(['societe_id' => $societe['id'], 'periode_id' => $periode['id'], 'type' => 'TVA'])->delete();
        $db->table('anomalies')->where(['societe_id' => $societe['id'], 'periode_id' => $periode['id']])->delete();
        (new RuleEngine())->executer((int) $societe['id'], (int) $periode['id']);

        $wf  = new WorkflowService();
        $id  = (new DeclarationService())->generer((int) $societe['id'], (int) $periode['id'], 'TVA', $comptable);
        $essayer = function (string $qui, int $uid, string $action, ?string $commentaire = null, array $extra = []) use ($wf, $id) {
            try {
                $r = $wf->transitionner($id, $action, $uid, $commentaire, $extra);
                CLI::write(sprintf('  [OK]     %-9s %s : %s -> %s%s', $qui, $action, $r['de'], $r['vers'], $r['score'] !== null ? sprintf(' (score %s, %s)', $r['score'], $r['niveau']) : ''), 'green');
            } catch (WorkflowException $e) {
                CLI::write(sprintf('  [REFUSÉ] %-9s %s : %s', $qui, $action, $e->getMessage()), 'red');
            }
        };

        $d = $db->table('declarations')->where('id', $id)->get()->getRowArray();
        CLI::write('1. Le comptable génère la déclaration de TVA : statut ' . $d['statut'] . ', TVA à payer ' . number_format((float) $d['montant_total'], 0, ',', ' ') . ' Ar, échéance ' . $d['date_echeance'], 'yellow');

        CLI::write('2. Il tente de la soumettre alors que des anomalies bloquantes sont ouvertes', 'yellow');
        $essayer('comptable', $comptable, 'soumettre');

        CLI::write('3. Il traite les anomalies bloquantes (simulation : en vrai, il corrige les écritures puis relance les règles)', 'yellow');
        $nb = $db->table('anomalies')->where(['societe_id' => $societe['id'], 'periode_id' => $periode['id'], 'severite' => 'bloquante', 'statut' => 'ouverte'])
            ->update(['statut' => 'corrigee', 'traitee_par' => $comptable]);
        CLI::write('  ' . $db->affectedRows() . ' anomalie(s) bloquante(s) passée(s) à « corrigee »');

        CLI::write('4. Il soumet la déclaration', 'yellow');
        $essayer('comptable', $comptable, 'soumettre');

        CLI::write('5. Le comptable essaie de valider lui-même, puis le responsable renvoie sans commentaire', 'yellow');
        $essayer('comptable', $comptable, 'valider');
        $essayer('responsable', $resp, 'renvoyer');

        CLI::write('6. Le responsable renvoie en correction avec un commentaire, le comptable resoumet', 'yellow');
        $essayer('responsable', $resp, 'renvoyer', 'Justifier la TVA déduite sur AC-002 avant validation.');
        $essayer('comptable', $comptable, 'soumettre');

        CLI::write('7. Le responsable valide, dépose (référence de virement obligatoire) puis archive', 'yellow');
        $essayer('responsable', $resp, 'valider');
        $essayer('responsable', $resp, 'deposer');
        $essayer('responsable', $resp, 'deposer', null, ['reference_virement' => 'VIR-2026-10-0001']);
        $essayer('responsable', $resp, 'archiver');

        CLI::newLine();
        CLI::write('Historique des validations', 'yellow');
        $rows = $db->table('validations v')->select('v.created_at, u.nom, v.etape, v.decision, v.commentaire')->join('utilisateurs u', 'u.id = v.utilisateur_id')
            ->where('v.declaration_id', $id)->orderBy('v.id')->get()->getResultArray();
        CLI::table(array_map(fn ($r) => [$r['created_at'], $r['nom'], $r['etape'], $r['decision'], (string) $r['commentaire']], $rows), ['Date', 'Utilisateur', 'Étape', 'Décision', 'Commentaire']);

        $v = (new AuditService())->verifierChaine();
        CLI::write(sprintf('Journal d\'audit : %d ligne(s), chaîne de hash %s', $v['nb'], $v['ok'] ? 'INTACTE' : 'ROMPUE à la ligne ' . $v['premiere_erreur']), $v['ok'] ? 'green' : 'red');

        return EXIT_SUCCESS;
    }
}
