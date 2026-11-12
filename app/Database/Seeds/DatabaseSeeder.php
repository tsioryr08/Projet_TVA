<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Point d'entrée : php spark db:seed DatabaseSeeder
 * L'ordre respecte les clés étrangères.
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call('RolesSeeder');
        $this->call('UtilisateursSeeder');
        $this->call('SocietesSeeder');
        $this->call('PeriodesFiscalesSeeder');
        $this->call('PlanComptableSeeder');
        $this->call('TauxFiscauxSeeder');
        $this->call('BaremesIrsaSeeder');
        $this->call('ReglesControleSeeder');
        $this->call('DemoDataSeeder');
    }
}
