<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class ListAno extends BaseCommand
{
    protected $group = 'Dev';
    protected $name = 'list:ano';
    protected $description = 'Liste les anomalies';
    protected $usage = 'list:ano';

    public function run(array $params)
    {
        $db = Database::connect();
        $rows = $db->table('anomalies a')
            ->select('a.id, a.societe_id, a.periode_id, a.severite, a.statut, r.code AS regle_code, r.active AS regle_active')
            ->join('regles_controle r', 'r.id = a.regle_id')
            ->orderBy('a.id')
            ->get()->getResultArray();
        foreach ($rows as $r) {
            CLI::write($r['id'] . ' | soc=' . $r['societe_id'] . ' per=' . $r['periode_id'] . ' | ' . $r['regle_code'] . ' active=' . $r['regle_active'] . ' | ' . $r['severite'] . ' | ' . $r['statut']);
        }
        CLI::newLine();
        CLI::write('Total: ' . count($rows));
    }
}