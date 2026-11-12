<?php

namespace App\Commands;

use App\Libraries\Workflow\AuditService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Vérifie l'intégrité du journal d'audit (chaîne de hash). Usage : php spark audit:verifier */
class VerifierAudit extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'audit:verifier';
    protected $description = 'Vérifie que le journal d\'audit n\'a été ni modifié ni amputé.';
    protected $usage       = 'audit:verifier';

    public function run(array $params)
    {
        $v = (new AuditService())->verifierChaine();
        if ($v['ok']) {
            CLI::write(sprintf('Journal intact : %d ligne(s) vérifiée(s).', $v['nb']), 'green');

            return EXIT_SUCCESS;
        }
        CLI::error(sprintf('Journal ALTÉRÉ à la ligne %d : %s.', $v['premiere_erreur'], $v['raison']));

        return EXIT_ERROR;
    }
}
