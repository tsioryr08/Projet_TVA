<?php

namespace App\Commands;

use App\Libraries\Score\CalculScore;
use App\Models\PeriodeFiscaleModel;
use App\Models\SocieteModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Calcule et affiche le score de risque d'une période (les anomalies doivent déjà exister :
 * lancer php spark regles:executer avant).
 * Usage : php spark score:calculer [annee] [mois]   (défaut : 2026 10)
 */
class CalculerScore extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'score:calculer';
    protected $description = 'Calcule le score de risque d\'une période et affiche son explication par règle.';
    protected $usage       = 'score:calculer [annee] [mois] [--societe id]';

    public function run(array $params)
    {
        $annee = (int) ($params[0] ?? 2026);
        $mois  = (int) ($params[1] ?? 10);

        $idSociete = CLI::getOption('societe');
        $societe   = $idSociete ? (new SocieteModel())->find((int) $idSociete) : (new SocieteModel())->first();
        $periode   = $societe === null ? null : (new PeriodeFiscaleModel())->where(['societe_id' => $societe['id']] + ['annee' => $annee, 'mois' => $mois])->first();

        if ($societe === null || $periode === null) {
            CLI::error('Société ou période introuvable : lancez d\'abord php spark db:seed DatabaseSeeder.');

            return EXIT_ERROR;
        }

        $res = (new CalculScore())->calculer((int) $societe['id'], (int) $periode['id']);
        $d   = $res['detail'];

        CLI::write(sprintf('%s - période %02d/%d', $societe['raison_sociale'], $mois, $annee), 'yellow');
        CLI::newLine();
        CLI::write(sprintf('SCORE DE RISQUE : %s / 100  (niveau %s)', $res['score'], strtoupper($res['niveau'])), $res['score'] >= 50 ? 'red' : 'green');
        CLI::newLine();

        CLI::table(
            array_map(fn ($r) => [$r['code'], (string) $r['nb_anomalies'], (string) $r['poids'], (string) round($r['points'], 2), (string) $r['contribution']], $d['regles']),
            ['Règle', 'Anomalies', 'Poids', 'Points', 'Contribution au score']
        );

        CLI::write(sprintf('Somme des points : %s x multiplicateur %s = %s', $d['somme_points'], $d['multiplicateur'], round($d['somme_points'] * $d['multiplicateur'], 2)));
        foreach ($d['historique']['declencheurs'] as $h) {
            CLI::write(sprintf('+ %s (historique) : redressement du %s sur %s (%s Ar) concernant %s', $d['historique']['majoration'], $h['date_controle'], $h['type_impot'], number_format($h['redressement_montant'], 0, ',', ' '), $h['regle']));
        }
        if ($d['plafonne']) {
            CLI::write(sprintf('Score brut %s, plafonné à 100.', $d['score_brut']), 'yellow');
        }
        CLI::write(sprintf('Anomalies bloquantes ouvertes : %d%s', $d['anomalies_bloquantes_ouvertes'], $d['anomalies_bloquantes_ouvertes'] > 0 ? ' (la validation est bloquée)' : ''));

        return EXIT_SUCCESS;
    }
}
