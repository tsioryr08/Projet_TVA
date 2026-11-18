<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class CountRows extends BaseCommand
{
    protected $group = 'Dev';
    protected $name = 'count:rows';
    protected $description = 'Compte les lignes des tables critiques';
    protected $usage = 'count:rows';

    public function run(array $params)
    {
        $db = Database::connect();
        foreach (['anomalies', 'scores_risque', 'journal_audit'] as $table) {
            $count = $db->table($table)->countAllResults();
            CLI::write(sprintf('%s : %d', $table, $count));
        }
    }
}