<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Jeu de données de démonstration : période d'octobre 2026 avec au moins une anomalie volontaire par règle.
 *
 *  R1 : VT-002, TVA collectée 300 000 au lieu de 400 000 (20 % de 2 000 000)
 *  R2 : AC-002, TVA déduite sans document ni NIF fournisseur ; AC-005, facture AC-001 comptabilisée en double
 *  R3 : OD-003, masse salariale comptabilisée 1 750 000 contre 1 800 000 de bruts sur les bulletins
 *  R4 : bulletin de Rakoto, IRSA retenu 20 000 au lieu de 25 700
 *  R5 : AC-004, honoraires de 2 000 000 à un non-résident, retenue de 100 000 au lieu de 200 000 (10 %)
 *  R6 : OD-001, débit 300 000 et crédit 250 000 ; OD-002, écriture datée du 30/09 dans la période d'octobre
 *
 * Témoins sans anomalie : VT-001, AC-001, AC-003, bulletins de Rabe et de Randria (ce dernier teste le minimum de perception).
 * Les calculs d'IRSA suivent le barème 2026 (base imposable = brut moins cotisations salariales).
 */
class DemoDataSeeder extends Seeder
{
    public function run()
    {
        $db  = $this->db;
        $now = date('Y-m-d H:i:s');

        $societeId = $db->table('societes')->get()->getRow()->id;
        $periodeId = $db->table('periodes_fiscales')->where(['societe_id' => $societeId, 'annee' => 2026, 'mois' => 10])->get()->getRow()->id;
        $compte    = fn (string $n) => $db->table('plan_comptable')->where('numero', $n)->get()->getRow()->id;

        // ---- Tiers (NIF fictifs) -------------------------------------------------------------
        // type, nom, nif, nif_valide, soumis_retenue, non_resident
        $tiers = [
            'alpha'    => ['client',      'Client Alpha SA',              '4000000011', 1, 0, 0],
            'beta'     => ['fournisseur', 'Fournisseur Beta SARL',        '5000000022', 1, 0, 0],
            'gamma'    => ['fournisseur', 'Gamma Services',               null,         0, 0, 0],
            'etranger' => ['prestataire', 'Consulting International Ltd', null,        0, 1, 1],
        ];
        $tid = [];
        foreach ($tiers as $k => [$type, $nom, $nif, $valide, $retenue, $nonResident]) {
            $db->table('tiers')->insert([
                'societe_id' => $societeId, 'type' => $type, 'raison_sociale' => $nom, 'nif' => $nif,
                'nif_valide' => $valide, 'soumis_retenue' => $retenue, 'non_resident' => $nonResident,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $tid[$k] = $db->insertID();
        }

        // ---- Documents et champs extraits ----------------------------------------------------
        $addDoc = function (string $type, string $fichier, int $tiersId, array $champs) use ($db, $societeId, $periodeId, $now) {
            $db->table('documents')->insert([
                'societe_id' => $societeId, 'periode_id' => $periodeId, 'tiers_id' => $tiersId,
                'type_document' => $type, 'nom_fichier' => $fichier, 'chemin' => 'writable/uploads/demo/' . $fichier,
                'hash_sha256' => hash('sha256', $fichier), 'statut_extraction' => 'extrait',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $docId = $db->insertID();
            foreach ($champs as $champ => $valeur) {
                $db->table('donnees_extraites')->insert([
                    'document_id' => $docId, 'champ' => $champ, 'valeur' => (string) $valeur, 'confiance' => 95.00, 'created_at' => $now,
                ]);
            }
            return $docId;
        };

        $doc1 = $addDoc('facture',   'facture_beta_001.pdf', $tid['beta'], ['montant_ttc' => 600000, 'date_document' => '2026-10-08', 'nif_emetteur' => '5000000022']);
        $doc2 = $addDoc('bordereau', 'bordereau_beta_002.pdf', $tid['beta'], ['montant_ttc' => 720000, 'date_document' => '2026-10-10', 'nif_emetteur' => '5000000022']);

        // ---- Écritures -----------------------------------------------------------------------
        $addEcriture = function (string $journal, string $piece, string $date, string $libelle, ?int $tiersId, ?int $docId, array $lignes) use ($db, $societeId, $periodeId, $now) {
            $db->table('ecritures')->insert([
                'societe_id' => $societeId, 'periode_id' => $periodeId, 'journal' => $journal, 'numero_piece' => $piece,
                'date_ecriture' => $date, 'libelle' => $libelle, 'tiers_id' => $tiersId, 'document_id' => $docId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = $db->insertID();
            $rows = [];
            foreach ($lignes as [$compteId, $debit, $credit, $codeTva]) {
                $rows[] = ['ecriture_id' => $id, 'compte_id' => $compteId, 'libelle' => $libelle, 'debit' => $debit, 'credit' => $credit, 'code_tva' => $codeTva];
            }
            $db->table('lignes_ecriture')->insertBatch($rows);
        };

        // VT-001 : correct (TVA 20 % de 1 000 000 = 200 000)
        $addEcriture('VT', 'VT-001', '2026-10-05', 'Vente à Client Alpha', $tid['alpha'], null, [
            [$compte('411'), 1200000, 0, null], [$compte('706'), 0, 1000000, null], [$compte('4457'), 0, 200000, 'TVA20'],
        ]);
        // VT-002 : R1 (TVA 300 000 au lieu de 400 000)
        $addEcriture('VT', 'VT-002', '2026-10-06', 'Vente à Client Alpha (TVA erronée)', $tid['alpha'], null, [
            [$compte('411'), 2300000, 0, null], [$compte('707'), 0, 2000000, null], [$compte('4457'), 0, 300000, 'TVA20'],
        ]);
        // AC-001 : correct, document lié, NIF valide
        $addEcriture('AC', 'AC-001', '2026-10-08', 'Achat à Beta', $tid['beta'], $doc1, [
            [$compte('601'), 500000, 0, null], [$compte('4456'), 100000, 0, 'TVA20'], [$compte('401'), 0, 600000, null],
        ]);
        // AC-002 : R2 (aucun document, fournisseur sans NIF)
        $addEcriture('AC', 'AC-002', '2026-10-09', 'Achat à Gamma (sans justificatif)', $tid['gamma'], null, [
            [$compte('607'), 800000, 0, null], [$compte('4456'), 160000, 0, 'TVA20'], [$compte('401'), 0, 960000, null],
        ]);
        // OD-001 : R6 (débit 300 000, crédit 250 000)
        $addEcriture('OD', 'OD-001', '2026-10-15', 'Encaissement client (écriture déséquilibrée)', $tid['alpha'], null, [
            [$compte('512'), 300000, 0, null], [$compte('411'), 0, 250000, null],
        ]);
        // AC-003 : correct (écriture 720 000, bordereau 720 000)
        $addEcriture('AC', 'AC-003', '2026-10-10', 'Achat à Beta (bordereau conforme)', $tid['beta'], $doc2, [
            [$compte('601'), 600000, 0, null], [$compte('4456'), 120000, 0, 'TVA20'], [$compte('401'), 0, 720000, null],
        ]);
        // AC-004 : R5 (honoraires 2 000 000 à un non-résident, retenue 100 000 au lieu de 200 000)
        $addEcriture('AC', 'AC-004', '2026-10-20', 'Honoraires Consulting International (retenue insuffisante)', $tid['etranger'], null, [
            [$compte('622'), 2000000, 0, null], [$compte('447'), 0, 100000, null], [$compte('401'), 0, 1900000, null],
        ]);
        // AC-005 : R2 doublon (même fournisseur, même numéro, même montant qu'AC-001)
        $addEcriture('AC', 'AC-001', '2026-10-18', 'Achat à Beta (facture comptabilisée en double)', $tid['beta'], $doc1, [
            [$compte('601'), 500000, 0, null], [$compte('4456'), 100000, 0, 'TVA20'], [$compte('401'), 0, 600000, null],
        ]);
        // OD-002 : R6 (écriture du 30 septembre saisie dans la période d'octobre)
        $addEcriture('OD', 'OD-002', '2026-09-30', 'Règlement client (date hors période)', $tid['alpha'], null, [
            [$compte('512'), 100000, 0, null], [$compte('411'), 0, 100000, null],
        ]);
        // OD-003 : R3 (masse salariale comptabilisée 1 750 000, bulletins 1 800 000)
        $addEcriture('PA', 'OD-003', '2026-10-31', 'Salaires du mois', null, null, [
            [$compte('641'), 1750000, 0, null], [$compte('421'), 0, 1750000, null],
        ]);

        // ---- Employés et bulletins -----------------------------------------------------------
        $emps = [
            'rabe'   => ['E001', 'Rabe',   'Jean'],
            'randria' => ['E002', 'Randria', 'Lala'],
            'rakoto' => ['E003', 'Rakoto', 'Hery'],
        ];
        $eid = [];
        foreach ($emps as $k => [$matricule, $nom, $prenom]) {
            $db->table('employes')->insert([
                'societe_id' => $societeId, 'matricule' => $matricule, 'nom' => $nom, 'prenom' => $prenom,
                'nb_charges' => 0, 'date_embauche' => '2024-01-15', 'actif' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $eid[$k] = $db->insertID();
        }

        // brut, cnaps_sal, ostie_sal, base_imposable, irsa_retenu, net, cnaps_pat, ostie_pat, fmfp
        $bulletins = [
            'rabe'    => [800000, 8000, 8000, 784000, 64300, 719700, 104000, 40000, 8000],  // IRSA correct
            'randria' => [400000, 4000, 4000, 392000,  3000, 389000,  52000, 20000, 4000],  // minimum de perception
            'rakoto'  => [600000, 6000, 6000, 588000, 20000, 568000,  78000, 30000, 6000],  // R4 : devrait être 25 700
        ];
        foreach ($bulletins as $k => $b) {
            $db->table('bulletins_paie')->insert([
                'employe_id' => $eid[$k], 'periode_id' => $periodeId, 'salaire_brut' => $b[0],
                'cotisation_cnaps_salarie' => $b[1], 'cotisation_ostie_salarie' => $b[2], 'base_imposable' => $b[3],
                'reduction_charges' => 0, 'irsa_retenu' => $b[4], 'net_a_payer' => $b[5],
                'cotisation_cnaps_patronale' => $b[6], 'cotisation_ostie_patronale' => $b[7], 'fmfp_patronale' => $b[8],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ---- Historique de contrôle fiscal (alimente le facteur historique du score) --------
        $db->table('controles_fiscaux_historique')->insert([
            'societe_id' => $societeId, 'date_controle' => '2025-03-15', 'type_impot' => 'TVA', 'regle_code' => 'R2',
            'motif' => 'TVA déductible sans justificatif suffisant', 'redressement_montant' => 4500000,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
