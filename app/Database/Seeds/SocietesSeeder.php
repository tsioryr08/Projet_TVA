<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Société fictive pour la démonstration (NIF et STAT inventés).
 */
class SocietesSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('societes')->insert([
            'raison_sociale' => 'Société Démo Fiscalia SARL',
            'nif'            => '3000000001',
            'stat'           => '47111 11 2020 0 10001',
            'adresse'        => 'Antananarivo, Madagascar',
            'activite'       => 'Négoce et prestations de services',
            'regime_fiscal'  => 'IS',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
    }
}
