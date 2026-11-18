<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Score\SimulateurScore;
use App\Models\PeriodeFiscaleModel;
use App\Models\SocieteModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use InvalidArgumentException;

/**
 * Simule le score d'une période si des anomalies étaient corrigées ou justifiées. N'écrit RIEN en base.
 * Usage : php spark score:simuler 2026 10 --societe 1 --corriger 12,13 --justifier 14 [--role comptable]
 */
class SimulerScore extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'score:simuler';
    protected $description = 'Simule le score de risque d\'une période si des anomalies étaient corrigées ou justifiées (aucune écriture).';
    protected $usage       = 'score:simuler [annee] [mois] [--societe id] [--corriger ids] [--justifier ids] [--role role]';
    protected $options     = [
        '--societe'   => 'Identifiant de la société (défaut : la première).',
        '--corriger'  => 'Identifiants d\'anomalies à simuler comme corrigées, séparés par des virgules.',
        '--justifier' => 'Identifiants d\'anomalies à simuler comme justifiées, séparés par des virgules.',
        '--role'      => 'Rôle simulé : comptable, responsable_fiscal (défaut) ou admin.',
    ];

    public function run(array $params)
    {
        $annee = (int) ($params[0] ?? 2026);
        $mois  = (int) ($params[1] ?? 10);
        $role  = (string) (CLI::getOption('role') ?: 'responsable_fiscal');

        if (! in_array($role, ['comptable', 'responsable_fiscal', 'admin'], true)) {
            CLI::error('Rôle inconnu : « ' . $role . ' » (comptable, responsable_fiscal ou admin).');

            return EXIT_ERROR;
        }

        $idSociete = CLI::getOption('societe');
        $societe   = $idSociete ? (new SocieteModel())->find((int) $idSociete) : (new SocieteModel())->first();
        $periode   = $societe === null ? null : (new PeriodeFiscaleModel())->where(['societe_id' => $societe['id'], 'annee' => $annee, 'mois' => $mois])->first();

        if ($societe === null || $periode === null) {
            CLI::error('Société ou période introuvable : lancez d\'abord php spark db:seed DatabaseSeeder.');

            return EXIT_ERROR;
        }

        // Construction des choix : une anomalie citée dans les deux listes est refusée.
        $choix = [];
        foreach (['corriger' => 'corrigee', 'justifier' => 'justifiee'] as $option => $statut) {
            foreach (array_filter(array_map('trim', explode(',', (string) (CLI::getOption($option) ?? '')))) as $id) {
                if (! ctype_digit($id)) {
                    CLI::error(sprintf('Identifiant invalide dans --%s : « %s ».', $option, $id));

                    return EXIT_ERROR;
                }
                if (isset($choix[(int) $id])) {
                    CLI::error(sprintf('L\'anomalie n° %d est citée dans --corriger et --justifier.', (int) $id));

                    return EXIT_ERROR;
                }
                $choix[(int) $id] = $statut;
            }
        }

        try {
            $r = (new SimulateurScore())->simuler((int) $societe['id'], (int) $periode['id'], $choix, $role);
        } catch (InvalidArgumentException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write(sprintf('%s - période %02d/%d (rôle simulé : %s)', $societe['raison_sociale'], $mois, $annee, $role), 'yellow');
        CLI::newLine();
        CLI::write(sprintf('SCORE ACTUEL   : %s / 100  (niveau %s)', $r['score_actuel'], strtoupper($r['niveau_actuel'])));
        CLI::write(sprintf('SCORE PROJETÉ  : %s / 100  (niveau %s), variation %+.1f', $r['score_projete'], strtoupper($r['niveau_projete']), $r['delta']), $r['delta'] < 0 ? 'green' : 'white');
        CLI::write(sprintf('Anomalies bloquantes ouvertes : %d avant, %d après', $r['bloquantes_avant'], $r['bloquantes_apres']));
        CLI::write(
            $r['soumettable_apres'] ? 'La déclaration pourrait être soumise.' : sprintf('Resterait bloquée : %d anomalie(s) bloquante(s).', $r['bloquantes_apres']),
            $r['soumettable_apres'] ? 'green' : 'red'
        );
        CLI::newLine();

        CLI::table(
            array_map(static fn ($d) => [$d['code'], (string) $d['nb_anomalies'], (string) $d['points_avant'], (string) $d['points_apres'], (string) $d['contribution_avant'], (string) $d['contribution_apres']], $r['detail_regles']),
            ['Règle', 'Anomalies', 'Points avant', 'Points après', 'Contribution avant', 'Contribution après']
        );
        CLI::write('Simulation : rien n\'est enregistré.', 'yellow');

        return EXIT_SUCCESS;
    }
}
