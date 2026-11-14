<?php

namespace App\Commands;

use App\Libraries\Anomalies\AnomalieException;
use App\Libraries\Anomalies\AnomalieService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Traite une anomalie.
 * Usage : php spark anomalies:traiter <id> <justifier|corriger|ignorer|rouvrir> [--commentaire "texte"] [--par email]
 * Utilisateur par défaut : comptable@demo.mg
 */
class AnomaliesTraiter extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'anomalies:traiter';
    protected $description = 'Justifie, corrige, ignore ou rouvre une anomalie, puis recalcule le score.';
    protected $usage       = 'anomalies:traiter <id> <action> [--commentaire "texte"] [--par email]';

    public function run(array $params)
    {
        $id     = (int) ($params[0] ?? 0);
        $action = (string) ($params[1] ?? '');
        $email  = (string) (CLI::getOption('par') ?: 'comptable@demo.mg');
        $user   = Database::connect()->table('utilisateurs')->where('email', $email)->get()->getRowArray();

        if ($id === 0 || $action === '' || $user === null) {
            CLI::error('Usage : php spark anomalies:traiter <id> <justifier|corriger|ignorer|rouvrir> [--commentaire "..."] [--par email]');

            return EXIT_ERROR;
        }

        $commentaire = CLI::getOption('commentaire');

        try {
            $r = (new AnomalieService())->traiter($id, $action, (int) $user['id'], is_string($commentaire) ? $commentaire : null);
        } catch (AnomalieException $e) {
            CLI::write('[REFUSÉ] ' . $e->getMessage(), 'red');

            return EXIT_ERROR;
        }

        CLI::write(sprintf('[OK] Anomalie %d (%s) : %s -> %s', $r['anomalie_id'], $r['regle'], $r['de'], $r['vers']), 'green');
        CLI::write(sprintf('  Score : %s (%s) -> %s (%s), variation %+.1f', $r['score_avant'], $r['niveau_avant'], $r['score_apres'], $r['niveau_apres'], $r['variation']));
        CLI::write(sprintf('  Anomalies bloquantes ouvertes : %d%s', $r['bloquantes_ouvertes'], $r['validation_bloquee'] ? ' (validation bloquée)' : ' (validation possible)'));

        return EXIT_SUCCESS;
    }
}
