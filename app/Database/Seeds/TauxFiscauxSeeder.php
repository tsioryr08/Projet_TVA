<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Paramètres légaux versionnés par date d'effet.
 *
 * verifie = 1 : valeur lue dans le Code des impôts "suivant la Loi de Finances 2025" (280 pages, DGI),
 *               article indiqué dans source_legale. Date d'effet 2025-01-01 (à confirmer).
 * verifie = 0 : valeur NON présente dans ce Code (cotisations sociales, salaire minimum, échéances
 *               qui relèvent du Code des procédures fiscales) ou non confirmée.
 *
 * Si la LFI 2026 ou la LFR 2026 (loi n° 2026-004) change une valeur : AJOUTER une ligne avec la
 * nouvelle date_effet, ne pas écraser l'ancienne.
 */
class TauxFiscauxSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $d25 = '2025-01-01';
        $cdi = 'Code des impôts (LFI 2025) ';

        $lignes = [
            // code, libelle, valeur, unite, date_effet, source_legale, verifie
            // --- TVA ---
            ['TVA_NORMAL',                     'TVA - taux normal',                                          20,        'pourcent',    $d25, $cdi . 'art. 06.01.12', 1],
            ['TVA_REDUIT_GAZ',                 'TVA - taux réduit (gaz butane et son contenant)',            10,        'pourcent',    $d25, $cdi . 'art. 06.01.12', 1],
            ['TVA_EXPORT',                     'TVA - taux des exportations de biens et services',            0,        'pourcent',    $d25, $cdi . 'art. 06.01.12', 1],
            ['TVA_SEUIL_ASSUJETTISSEMENT',     'TVA - seuil d\'assujettissement obligatoire (CA annuel HT)', 400000000, 'montant',    $d25, $cdi . 'art. 06.01.04', 1],
            // --- IRSA ---
            ['IRSA_MINIMUM',                   'IRSA - impôt minimum (mensuel)',                           3000,        'montant',     $d25, $cdi . 'art. 01.03.16', 1],
            ['IRSA_ARRONDI_BASE',              'IRSA - la base est arrondie à la centaine d\'Ariary inférieure', 100,   'montant',     $d25, $cdi . 'art. 01.03.16', 1],
            ['IRSA_REDUCTION_CHARGE',          'IRSA - réduction par personne à charge (mensuelle, revenu imposable > 350 000)', 2000, 'montant', $d25, $cdi . 'art. 01.03.19', 1],
            ['IRSA_DEDUCTION_RETRAITE_PLAFOND','IRSA - déduction des cotisations retraite (plafond en % du brut)', 10,  'pourcent',    $d25, $cdi . 'art. 01.03.09 1°', 1],
            ['IRSA_DEDUCTION_SANTE_PLAFOND',   'IRSA - déduction des cotisations santé (plafond en % du brut)',      2,  'pourcent',    $d25, $cdi . 'art. 01.03.09 2°', 1],
            // --- Autres impôts et retenues ---
            ['IR_TAUX_NORMAL',                 'Impôt sur les revenus - taux',                               20,        'pourcent',    $d25, $cdi . 'art. 01.01.14 I-A', 1],
            ['IRCM_TAUX',                      'Impôt sur le revenu des capitaux mobiliers - taux',          20,        'pourcent',    $d25, $cdi . 'art. 01.04.04', 1],
            ['IMPOT_SYNTHETIQUE',              'Impôt synthétique - taux',                                    5,        'pourcent',    $d25, $cdi . 'art. 01.02.05', 1],
            ['RETENUE_NON_RESIDENT_SERVICES',  'Retenue sur services payés à des non-résidents (sous réserve des conventions fiscales)', 10, 'pourcent', $d25, $cdi . 'art. 01.01.14 II-A', 1],
            ['RETENUE_DELAI_JOUR',             'Retenue non-résidents : versement avant le 15 du mois suivant la retenue', 15, 'jour', $d25, $cdi . 'art. 01.01.14 II-A', 1],
            // --- Pénalités (alimentent la pondération du score) ---
            ['PENALITE_RETARD_MOIS1_CA_GE_400M',  'Pénalité de retard, 1er mois (CA >= 400 M Ar)',            3,        'pourcent',    $d25, $cdi . 'art. 20.01.53', 1],
            ['PENALITE_RETARD_MOIS1_CA_100_400M', 'Pénalité de retard, 1er mois (CA de 100 à 400 M Ar)',      2,        'pourcent',    $d25, $cdi . 'art. 20.01.53', 1],
            ['PENALITE_RETARD_PAR_MOIS',       'Pénalité de retard, par mois suivant (ou par mois si CA < 100 M Ar)', 1, 'pourcent',   $d25, $cdi . 'art. 20.01.53', 1],
            ['PENALITE_RETARD_MINIMUM',        'Pénalité de retard - minimum',                            10000,        'montant',     $d25, $cdi . 'art. 20.01.53', 1],
            ['PENALITE_DEFAUT_DEPOT_GRANDE',   'Défaut de dépôt de déclaration (CA >= 400 M Ar)',        200000,        'montant',     $d25, $cdi . 'art. 20.01.52', 1],
            ['AMENDE_INSUFFISANCE',            'Amende pour insuffisance, inexactitude ou omission (% du complément de droits)', 40, 'pourcent', $d25, $cdi . 'art. 20.01.54', 1],
            ['AMENDE_TVA_DEDUCTION_INDUE',     'Amende TVA : taxe déduite à tort ou fraudée (% de la taxe)', 80,        'pourcent',    $d25, $cdi . 'art. 20.01.54.2 C', 1],
            ['AMENDE_TVA_VENTE_NON_COMPTABILISEE', 'Amende TVA : vente non passée dans les écritures (% de la taxe)', 150, 'pourcent', $d25, $cdi . 'art. 20.01.54.2 D', 1],
            // --- Hors Code des impôts : à confirmer ---
            ['ECHEANCE_JOUR',                  'Jour limite de déclaration TVA et IRSA (mois M+1)',          15,        'jour',        '2026-01-01', 'Code des procédures fiscales (art. I-104, I-20) - non fourni, à lire', 0],
            ['CNAPS_SALARIE',                  'CNaPS - part salariale',                                      1,        'pourcent',    '2026-01-01', 'Hors Code des impôts - source secondaire, à confirmer', 0],
            ['CNAPS_EMPLOYEUR',                'CNaPS - part patronale',                                     13,        'pourcent',    '2026-01-01', 'Hors Code des impôts - source secondaire, à confirmer', 0],
            ['OSTIE_SALARIE',                  'OSTIE - part salariale',                                      1,        'pourcent',    '2026-01-01', 'Hors Code des impôts - source secondaire, à confirmer', 0],
            ['OSTIE_EMPLOYEUR',                'OSTIE - part patronale',                                      5,        'pourcent',    '2026-01-01', 'Hors Code des impôts - source secondaire, à confirmer', 0],
            ['FMFP_EMPLOYEUR',                 'FMFP - formation professionnelle (patronale)',                1,        'pourcent',    '2026-01-01', 'Hors Code des impôts - source secondaire, à confirmer', 0],
            ['PLAFOND_COTIS_COEF',             'Plafond des cotisations (multiple du salaire minimum)',       8,        'coefficient', '2026-01-01', 'Hors Code des impôts - source secondaire, à confirmer', 0],
            ['SALAIRE_MINIMUM',                'Salaire minimum d\'embauche (mensuel)',                  315000,        'montant',     '2026-10-01', 'Hors Code des impôts - valeur relevée dans la presse (oct. 2026), à confirmer', 0],
        ];

        $rows = array_map(fn ($l) => [
            'code' => $l[0], 'libelle' => $l[1], 'valeur' => $l[2], 'unite' => $l[3],
            'date_effet' => $l[4], 'date_fin' => null, 'source_legale' => $l[5], 'verifie' => $l[6],
            'created_at' => $now, 'updated_at' => $now,
        ], $lignes);

        $this->db->table('taux_fiscaux')->insertBatch($rows);
    }
}
