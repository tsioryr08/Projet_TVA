<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Catalogue des 6 règles (celui du plan de travail du binôme).
 * Les seuils, tolérances et poids vivent ici, en base : modifiables sans toucher au code.
 * Chaque code (R1 à R6) est relié à une classe dans app/Libraries/Regles/ (voir RuleEngine::REGLES).
 *
 * Gravité : info (0 point), moyenne (1), elevee (2), bloquante (3). Une anomalie bloquante ouverte
 * empêche la validation dans le workflow.
 */
class ReglesControleSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $regles = [
            [
                'code' => 'R1', 'intitule' => 'TVA collectée conforme au taux en vigueur',
                'description' => 'Pour chaque écriture de vente avec TVA collectée : TVA comptabilisée = base HT x taux normal en vigueur à la date de l\'écriture (table taux_fiscaux), avec une tolérance.',
                'gravite' => 'elevee', 'poids' => 1.5,
                'parametres' => ['taux_code' => 'TVA_NORMAL', 'tolerance_absolue' => 100],
                'message_erreur' => 'La TVA collectée ne correspond pas à la base HT au taux en vigueur.',
                'action_corrective' => 'Vérifier le taux appliqué et le montant HT de la facture, puis corriger l\'écriture.',
                'source_legale' => 'Code des impôts art. 06.01.12 (taux normal 20 %), art. 06.01.11 (base imposable)', 'active' => 1,
            ],
            [
                'code' => 'R2', 'intitule' => 'TVA déductible justifiée, sans doublon de facture',
                'description' => 'Toute TVA déduite doit figurer sur une facture d\'un assujetti (NIF valide) liée à un document. Une même facture (même tiers, même numéro, même montant) ne doit pas être comptabilisée deux fois. Le paiement bancaire exigé par l\'art. 06.01.17 n\'est pas contrôlé (pas de données de paiement).',
                'gravite' => 'elevee', 'poids' => 2.0,
                'parametres' => ['exiger_document' => true, 'exiger_nif_valide' => true, 'detecter_doublons' => true, 'journal_achats' => 'AC'],
                'message_erreur' => 'TVA déduite sans justificatif complet, ou facture comptabilisée en double.',
                'action_corrective' => 'Rattacher la facture et renseigner le NIF du fournisseur, retirer la déduction, ou supprimer l\'écriture en double.',
                'source_legale' => 'Code des impôts art. 06.01.17 1° (déduction), 06.01.18 (exclusions), 06.01.21 ; Code des procédures fiscales art. IV-21 (facture) - à lire', 'active' => 1,
            ],
            [
                'code' => 'R3', 'intitule' => 'Base IRSA cohérente avec la masse salariale comptabilisée',
                'description' => 'La somme des salaires bruts des bulletins de la période doit correspondre au total débit du compte de rémunérations du personnel, avec une tolérance.',
                'gravite' => 'moyenne', 'poids' => 1.0,
                'parametres' => ['compte_masse_salariale' => '641', 'tolerance_absolue' => 1000],
                'message_erreur' => 'Écart entre la masse salariale des bulletins et la comptabilité.',
                'action_corrective' => 'Rapprocher les bulletins et les écritures de paie, puis corriger l\'une ou l\'autre.',
                'source_legale' => 'Code des impôts art. 01.03.07 (revenus imposables), art. 06.01.26 (comptabilité régulière)', 'active' => 1,
            ],
            [
                'code' => 'R4', 'intitule' => 'IRSA retenu conforme au barème',
                'description' => 'L\'IRSA retenu sur chaque bulletin doit égaler l\'IRSA recalculé : base arrondie à la centaine inférieure, barème en vigueur à la date de la période, réduction pour personnes à charge, minimum de perception.',
                'gravite' => 'elevee', 'poids' => 1.5,
                'parametres' => ['tolerance_absolue' => 100],
                'message_erreur' => 'IRSA retenu différent de l\'IRSA calculé selon le barème.',
                'action_corrective' => 'Recalculer l\'IRSA du bulletin et régulariser la retenue.',
                'source_legale' => 'Code des impôts art. 01.03.16 (barème, minimum 3 000 Ar, base arrondie à la centaine inférieure), art. 01.03.09 (déductions), art. 01.03.19 (personnes à charge)', 'active' => 1,
            ],
            [
                'code' => 'R5', 'intitule' => 'Retenue à la source conforme (services payés à des non-résidents)',
                'description' => 'Pour un paiement de services à un non-résident : retenue comptabilisée = base x taux de retenue (10 %). Seule la retenue sur non-résidents est contrôlée ; l\'impôt synthétique sur vendeurs non immatriculés et l\'IRSA reversé seront ajoutés après lecture du Code des procédures fiscales.',
                'gravite' => 'elevee', 'poids' => 2.0,
                'parametres' => ['retenue_taux_code' => 'RETENUE_NON_RESIDENT_SERVICES', 'compte_charge' => '622', 'tolerance_absolue' => 100],
                'message_erreur' => 'Retenue à la source absente ou au mauvais taux.',
                'action_corrective' => 'Comptabiliser la retenue au taux légal et la reverser avant le 15 du mois suivant.',
                'source_legale' => 'Code des impôts art. 01.01.14 II-A (10 % sur services de non-résidents, versement avant le 15 du mois suivant)', 'active' => 1,
            ],
            [
                'code' => 'R6', 'intitule' => 'Périodes et intégrité des écritures',
                'description' => 'Chaque écriture est équilibrée (débit = crédit), datée dans la période contrôlée, et la période est ouverte.',
                'gravite' => 'bloquante', 'poids' => 1.0,
                'parametres' => ['tolerance_absolue' => 0, 'exiger_periode_ouverte' => true, 'controler_hors_periode' => true],
                'message_erreur' => 'Écriture déséquilibrée, hors période, ou période non ouverte.',
                'action_corrective' => 'Corriger les montants, la date de l\'écriture, ou rouvrir la période avec validation.',
                'source_legale' => 'Code des impôts art. 06.01.26 (comptabilité régulière) ; Plan Comptable Général - à préciser', 'active' => 1,
            ],
        ];

        $rows = array_map(function ($r) use ($now) {
            $r['parametres'] = json_encode($r['parametres'], JSON_UNESCAPED_UNICODE);
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
            return $r;
        }, $regles);

        $this->db->table('regles_controle')->insertBatch($rows);
    }
}
