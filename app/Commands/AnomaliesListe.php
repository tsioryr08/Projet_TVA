<?php

namespace App\Commands;

use App\Libraries\Anomalies\AnomalieService;
use App\Models\PeriodeFiscaleModel;
use App\Models\SocieteModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Liste les anomalies d'une période (avec leur id, nécessaire pour les traiter).
 * Usage : php spark anomalies:liste [annee] [mois] [--societe id] [--statut ouverte] [--regle R2] [--severite bloquante]
 */
class AnomaliesListe extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'anomalies:liste';
    protected $description = 'Liste les anomalies d\'une période, avec filtres.';
    protected $usage       = 'anomalies:liste [annee] [mois] [--societe id] [--statut s] [--regle R] [--severite g]';

    public function run(array $params)
    {
        $idSociete = CLI::getOption('societe');
        $societe   = $idSociete ? (new SocieteModel())->find((int) $idSociete) : (new SocieteModel())->first();
        $periode   = $societe === null ? null : (new PeriodeFiscaleModel())->where(['societe_id' => $societe['id'], 'annee' => (int) ($params[0] ?? 2026), 'mois' => (int) ($params[1] ?? 10)])->first();
        if ($societe === null || $periode === null) {
            CLI::error('Société ou période introuvable.');

            return EXIT_ERROR;
        }

        $filtres = array_filter(['statut' => CLI::getOption('statut'), 'regle' => CLI::getOption('regle'), 'severite' => CLI::getOption('severite')], fn ($v) => is_string($v));
        $rows    = (new AnomalieService())->lister((int) $societe['id'], (int) $periode['id'], $filtres);

        CLI::write(sprintf('%s - période %02d/%d : %d anomalie(s)', $societe['raison_sociale'], $periode['mois'], $periode['annee'], count($rows)), 'yellow');
        CLI::table(array_map(fn ($r) => [
            (string) $r['id'], $r['regle_code'], $r['severite'], $r['statut'], number_format($r['montant_concerne'], 0, ',', ' '), mb_strimwidth($r['message'], 0, 70, '...'),
        ], $rows), ['Id', 'Règle', 'Gravité', 'Statut', 'Montant', 'Message']);

        return EXIT_SUCCESS;
    }
}
