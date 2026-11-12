<?php

namespace App\Commands;

use App\Libraries\Regles\RuleEngine;
use App\Models\PeriodeFiscaleModel;
use App\Models\SocieteModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Exécute les règles de contrôle sur une période et affiche les anomalies.
 * Usage : php spark regles:executer [annee] [mois]   (défaut : 2026 10)
 */
class ExecuterRegles extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'regles:executer';
    protected $description = 'Exécute les règles de contrôle (R1 à R6) sur une période et affiche les anomalies.';
    protected $usage       = 'regles:executer [annee] [mois]';

    public function run(array $params)
    {
        $annee = (int) ($params[0] ?? 2026);
        $mois  = (int) ($params[1] ?? 10);

        $societe = (new SocieteModel())->first();
        $periode = (new PeriodeFiscaleModel())->where(['annee' => $annee, 'mois' => $mois])->first();

        if ($societe === null || $periode === null) {
            CLI::error('Société ou période introuvable : lancez d\'abord php spark db:seed DatabaseSeeder.');

            return EXIT_ERROR;
        }

        CLI::write(sprintf('Contrôle de %s - période %02d/%d', $societe['raison_sociale'], $mois, $annee), 'yellow');
        $resume = (new RuleEngine())->executer((int) $societe['id'], (int) $periode['id']);

        CLI::newLine();
        CLI::table(
            array_map(fn ($r) => [$r['code'], $r['gravite'], (string) $r['nb'], number_format($r['montant'], 0, ',', ' ')], $resume),
            ['Règle', 'Gravité', 'Anomalies', 'Montant concerné (Ar)']
        );

        $rows = Database::connect()->table('anomalies a')
            ->select('r.code, a.severite, a.montant_concerne, a.message, (SELECT COUNT(*) FROM anomalie_preuves p WHERE p.anomalie_id = a.id) AS preuves')
            ->join('regles_controle r', 'r.id = a.regle_id')
            ->where(['a.societe_id' => $societe['id'], 'a.periode_id' => $periode['id']])
            ->orderBy('r.code')->orderBy('a.id')->get()->getResultArray();

        CLI::newLine();
        foreach ($rows as $r) {
            CLI::write(sprintf('[%s] %s (%s preuve(s))', $r['code'], $r['message'], $r['preuves']));
        }
        CLI::newLine();
        CLI::write(count($rows) . ' anomalie(s) enregistrée(s) dans la table anomalies.', 'green');

        return EXIT_SUCCESS;
    }
}
