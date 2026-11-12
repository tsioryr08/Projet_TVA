<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Barème IRSA mensuel, droit commun. Deux versions, choisies par date d'effet :
 *
 *  - 2025 : 5 tranches (0 % à 20 %). VÉRIFIÉ dans le Code des impôts (LFI 2025), art. 01.03.16 A.
 *  - 2026 : 6 tranches, avec une tranche à 25 % au-delà de 4 000 000 Ar. NON VÉRIFIÉ dans le texte officiel
 *           (verifie = 0) : plusieurs sources secondaires concordantes (presse malgache, cabinets) l'annoncent
 *           pour la LFI 2026, mais le Code des impôts suivant la LFI 2026 n'a pas encore été lu.
 *
 * Le moteur doit choisir, pour une période donnée, les lignes dont date_effet <= date de la période
 * et (date_fin est nulle ou >= date de la période).
 * Ne pas modifier ces lignes si la loi change à nouveau : ajouter une nouvelle version.
 */
class BaremesIrsaSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // [tranche, borne_min, borne_max, taux]
        $v2025 = [
            [1,      0, 350000,  0],
            [2, 350000, 400000,  5],
            [3, 400000, 500000, 10],
            [4, 500000, 600000, 15],
            [5, 600000,   null, 20],
        ];
        $v2026 = [
            [1,       0,  350000,  0],
            [2,  350000,  400000,  5],
            [3,  400000,  500000, 10],
            [4,  500000,  600000, 15],
            [5,  600000, 4000000, 20],
            [6, 4000000,    null, 25],
        ];

        $rows = [];
        foreach ($v2025 as $t) {
            $rows[] = $this->ligne($t, '2025-01-01', '2025-12-31', 'Code des impôts (LFI 2025) art. 01.03.16 A (droit commun)', 1, $now);
        }
        foreach ($v2026 as $t) {
            $rows[] = $this->ligne($t, '2026-01-01', null, 'LFI 2026 - source secondaire (tranche 25 % au-delà de 4 000 000 Ar), texte officiel à lire', 0, $now);
        }

        $this->db->table('baremes_irsa')->insertBatch($rows);
    }

    private function ligne(array $t, string $effet, ?string $fin, string $source, int $verifie, string $now): array
    {
        return [
            'tranche_numero' => $t[0], 'borne_min' => $t[1], 'borne_max' => $t[2], 'taux' => $t[3],
            'date_effet' => $effet, 'date_fin' => $fin, 'source_legale' => $source, 'verifie' => $verifie,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }
}
