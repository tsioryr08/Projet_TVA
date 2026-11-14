<?php

namespace App\Commands;

use App\Libraries\Dashboard\DashboardService;
use App\Models\PeriodeFiscaleModel;
use App\Models\SocieteModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Affiche les données du dashboard d'une période.
 * Usage : php spark dashboard:donnees [annee] [mois] [json]
 */
class DashboardDonnees extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'dashboard:donnees';
    protected $description = 'Affiche les données du dashboard de risque (ajouter json pour la sortie brute).';
    protected $usage       = 'dashboard:donnees [annee] [mois] [json] [--societe id]';

    public function run(array $params)
    {
        $idSociete = CLI::getOption('societe');
        $societe   = $idSociete ? (new SocieteModel())->find((int) $idSociete) : (new SocieteModel())->first();
        $periode   = $societe === null ? null : (new PeriodeFiscaleModel())->where(['societe_id' => $societe['id']] + ['annee' => (int) ($params[0] ?? 2026), 'mois' => (int) ($params[1] ?? 10)])->first();
        if ($societe === null || $periode === null) {
            CLI::error('Société ou période introuvable.');

            return EXIT_ERROR;
        }

        $d = (new DashboardService())->donnees((int) $societe['id'], (int) $periode['id']);

        if (($params[2] ?? '') === 'json') {
            CLI::write(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return EXIT_SUCCESS;
        }

        CLI::write(sprintf('%s - période %s', $d['societe']['raison_sociale'], $d['periode']['libelle']), 'yellow');
        CLI::write(sprintf('SCORE : %s/100 (%s)%s', $d['score']['valeur'], $d['score']['niveau'], $d['score']['variation'] === null ? '' : sprintf('  variation vs période précédente : %+.1f', $d['score']['variation'])), 'red');
        $k = $d['kpi'];
        CLI::write(sprintf('Anomalies : %d (ouvertes %d, bloquantes ouvertes %d) - montant ouvert %s Ar - taux de traitement %d %%%s', $k['nb_anomalies'], $k['nb_ouvertes'], $k['nb_bloquantes_ouvertes'], number_format($k['montant_concerne_ouvert'], 0, ',', ' '), $k['taux_traitement'], $k['validation_bloquee'] ? ' - VALIDATION BLOQUÉE' : ''));

        CLI::newLine();
        CLI::table(array_map(fn ($r) => [$r['code'], $r['impot'], (string) $r['nb'], (string) $r['nb_ouvertes'], number_format($r['montant'], 0, ',', ' ')], $d['par_regle']), ['Règle', 'Impôt', 'Anomalies', 'Ouvertes', 'Montant (Ar)']);

        CLI::write('Par impôt : ' . implode(' | ', array_map(fn ($i) => $i['impot'] . ' ' . $i['nb'], $d['par_impot'])));
        CLI::write('Par gravité : ' . implode(' | ', array_map(fn ($g) => $g['gravite'] . ' ' . $g['nb'], $d['par_gravite'])));
        CLI::write('Par statut : ' . implode(' | ', array_map(fn ($s) => $s['statut'] . ' ' . $s['nb'], $d['par_statut'])));

        CLI::newLine();
        CLI::write('Évolution du score : ' . implode(' | ', array_map(fn ($e) => $e['periode'] . ' = ' . $e['score'], $d['evolution_score'])));

        CLI::newLine();
        CLI::write('Plus grosses anomalies ouvertes :', 'yellow');
        foreach ($d['top_anomalies'] as $a) {
            CLI::write(sprintf('  [%s] %s Ar - %s', $a['regle'], number_format($a['montant'], 0, ',', ' '), $a['message']));
        }

        CLI::newLine();
        CLI::write('Échéances :', 'yellow');
        foreach ($d['echeances'] as $e) {
            CLI::write(sprintf('  %s %s : %s, échéance %s (%s jour(s)) - %s', $e['type'], $e['periode'], $e['statut'], $e['date_echeance'], $e['jours_restants'], strtoupper($e['etat'])));
        }

        return EXIT_SUCCESS;
    }
}
