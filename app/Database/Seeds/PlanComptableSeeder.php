<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Extrait du plan comptable. ATTENTION : numéros et libellés PROVISOIRES,
 * à comparer avec le Plan Comptable Général en vigueur à Madagascar.
 * La colonne role_fiscal permet aux règles de retrouver les comptes sans les écrire en dur.
 */
class PlanComptableSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $comptes = [
            // numero, libelle, classe, role_fiscal
            ['401',  'Fournisseurs',                                  4, null],
            ['411',  'Clients',                                       4, null],
            ['421',  'Personnel - rémunérations dues',                4, null],
            ['431',  'Organismes sociaux (CNaPS, OSTIE)',             4, 'cotisations'],
            ['4456', 'TVA déductible',                                4, 'tva_deductible'],
            ['4457', 'TVA collectée',                                 4, 'tva_collectee'],
            ['447',  'État - retenues à la source',                   4, 'retenue_source'],
            ['4471', 'État - IRSA retenu',                            4, 'irsa_retenu'],
            ['512',  'Banque',                                        5, null],
            ['531',  'Caisse',                                        5, null],
            ['601',  'Achats de matières et fournitures',             6, null],
            ['607',  'Achats de marchandises',                        6, null],
            ['622',  'Honoraires et rémunérations d\'intermédiaires', 6, null],
            ['641',  'Rémunérations du personnel',                    6, null],
            ['645',  'Charges sociales',                              6, null],
            ['706',  'Prestations de services',                       7, null],
            ['707',  'Ventes de marchandises',                        7, null],
        ];

        $rows = array_map(fn ($c) => [
            'numero' => $c[0], 'libelle' => $c[1], 'classe' => $c[2], 'role_fiscal' => $c[3],
            'created_at' => $now, 'updated_at' => $now,
        ], $comptes);

        $this->db->table('plan_comptable')->insertBatch($rows);
    }
}
