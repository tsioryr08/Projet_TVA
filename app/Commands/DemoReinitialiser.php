<?php

namespace App\Commands;

use App\Libraries\Regles\RuleEngine;
use App\Libraries\Score\CalculScore;
use App\Libraries\Workflow\AuditService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Remet la démonstration à son état de départ, en une commande :
 *  - supprime déclarations, validations, anomalies et scores ;
 *  - remet les règles à leurs valeurs initiales (poids, gravité, activation, seuils) ;
 *  - relance le moteur et le score sur octobre 2026 pour chaque société ;
 *  - conserve le journal d'audit (sa chaîne de hash doit rester intacte) et y note la réinitialisation.
 * Usage : php spark demo:reinitialiser
 */
class DemoReinitialiser extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'demo:reinitialiser';
    protected $description = 'Remet la démo à zéro : règles initiales, anomalies et scores recalculés pour chaque société.';
    protected $usage       = 'demo:reinitialiser';

    public function run(array $params)
    {
        $db = Database::connect();

        $db->table('declarations')->emptyTable();   // supprime aussi lignes et validations (cascade)
        $db->table('anomalies')->emptyTable();      // supprime aussi les preuves (cascade)
        $db->table('scores_risque')->emptyTable();
        $db->table('regles_controle')->emptyTable();
        Database::seeder()->call('ReglesControleSeeder');

        $moteur = new RuleEngine();
        $calcul = new CalculScore();
        $lignes = [];

        foreach ($db->table('societes')->orderBy('id')->get()->getResultArray() as $s) {
            $p = $db->table('periodes_fiscales')->where(['societe_id' => $s['id'], 'annee' => 2026, 'mois' => 10])->get()->getRowArray();
            if ($p === null) {
                continue;
            }
            $moteur->executer((int) $s['id'], (int) $p['id']);
            $r = $calcul->calculer((int) $s['id'], (int) $p['id'], true);
            $lignes[] = [(string) $s['id'], $s['raison_sociale'], (string) $r['detail']['nb_anomalies'], (string) $r['detail']['anomalies_bloquantes_ouvertes'], $r['score'] . ' / 100', strtoupper($r['niveau'])];
        }

        (new AuditService())->journaliser(null, 'reinitialiser_demo', 'demo', null, null, ['note' => 'Démo remise à l\'état initial']);

        CLI::write('Démo réinitialisée (octobre 2026) :', 'green');
        CLI::table($lignes, ['Id', 'Société', 'Anomalies', 'Bloquantes', 'Score', 'Niveau']);
        CLI::write('Règles remises à leurs valeurs initiales. Journal d\'audit conservé.');

        return EXIT_SUCCESS;
    }
}
