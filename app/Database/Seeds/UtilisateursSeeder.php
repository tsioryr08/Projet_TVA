<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Comptes de démonstration. Mot de passe commun : Demo@2026 (à changer hors démo).
 */
class UtilisateursSeeder extends Seeder
{
    public function run()
    {
        $now  = date('Y-m-d H:i:s');
        $role = fn (string $code) => $this->db->table('roles')->where('code', $code)->get()->getRow()->id;
        $hash = password_hash('Demo@2026', PASSWORD_DEFAULT);

        $this->db->table('utilisateurs')->insertBatch([
            ['role_id' => $role('comptable'),          'nom' => 'Comptable Démo',  'email' => 'comptable@demo.mg', 'mot_de_passe' => $hash, 'actif' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['role_id' => $role('responsable_fiscal'), 'nom' => 'Responsable Démo', 'email' => 'fiscal@demo.mg',    'mot_de_passe' => $hash, 'actif' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['role_id' => $role('admin'),              'nom' => 'Admin Démo',      'email' => 'admin@demo.mg',     'mot_de_passe' => $hash, 'actif' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
