<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Périodes mensuelles août à octobre 2026 pour la société de démonstration.
 * Octobre reste ouverte : c'est la période contrôlée pendant la démo.
 */
class PeriodesFiscalesSeeder extends Seeder
{
    public function run()
    {
        $now       = date('Y-m-d H:i:s');
        $societeId = $this->db->table('societes')->get()->getRow()->id;
        $statuts   = [8 => 'cloturee', 9 => 'cloturee', 10 => 'ouverte'];
        $rows      = [];

        foreach ($statuts as $mois => $statut) {
            $debut  = sprintf('2026-%02d-01', $mois);
            $rows[] = [
                'societe_id' => $societeId,
                'annee'      => 2026,
                'mois'       => $mois,
                'date_debut' => $debut,
                'date_fin'   => date('Y-m-t', strtotime($debut)),
                'statut'     => $statut,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->db->table('periodes_fiscales')->insertBatch($rows);
    }
}
