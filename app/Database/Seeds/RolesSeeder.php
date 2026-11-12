<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('roles')->insertBatch([
            ['code' => 'comptable',          'libelle' => 'Comptable',          'created_at' => $now],
            ['code' => 'responsable_fiscal', 'libelle' => 'Responsable fiscal', 'created_at' => $now],
            ['code' => 'admin',              'libelle' => 'Administrateur',     'created_at' => $now],
        ]);
    }
}
