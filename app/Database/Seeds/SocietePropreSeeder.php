<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seconde société de démonstration : « propre », sans aucune anomalie en octobre 2026.
 * Sert au contraste de la soutenance : score proche de 0 ici, score élevé pour la société « à risque ».
 * Mêmes types d'opérations que la société à risque, mais toutes conformes :
 *  - TVA collectée à 20 % exacte, TVA déduite avec document et NIF valide, pas de doublon ;
 *  - paiement à un non-résident avec retenue de 10 % ;
 *  - bulletins avec IRSA exact, masse salariale comptabilisée égale aux bulletins ;
 *  - écritures équilibrées et datées dans la période.
 * Peut être lancé seul sur une base existante : php spark db:seed SocietePropreSeeder
 */
class SocietePropreSeeder extends Seeder
{
    public function run()
    {
        $db  = $this->db;
        $now = date('Y-m-d H:i:s');

        // ---- Société et périodes --------------------------------------------------------------
        $db->table('societes')->insert([
            'raison_sociale' => 'Société Modèle Claritas SA', 'nif' => '3000000002', 'stat' => '47112 11 2018 0 20002',
            'adresse' => 'Antananarivo, Madagascar', 'activite' => 'Négoce et prestations de services', 'regime_fiscal' => 'IS',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $societeId = (int) $db->insertID();

        foreach ([8 => 'cloturee', 9 => 'cloturee', 10 => 'ouverte'] as $mois => $statut) {
            $debut = sprintf('2026-%02d-01', $mois);
            $db->table('periodes_fiscales')->insert([
                'societe_id' => $societeId, 'annee' => 2026, 'mois' => $mois, 'date_debut' => $debut,
                'date_fin' => date('Y-m-t', strtotime($debut)), 'statut' => $statut, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $periodeId = (int) $db->table('periodes_fiscales')->where(['societe_id' => $societeId, 'mois' => 10])->get()->getRow()->id;
        $compte    = fn (string $n) => $db->table('plan_comptable')->where('numero', $n)->get()->getRow()->id;

        // ---- Tiers (NIF fictifs) --------------------------------------------------------------
        $tiers = [
            'client'    => ['client',      'Client Omega SA',          '4000000044', 1, 0],
            'fourn'     => ['fournisseur', 'Fournisseur Sigma SARL',   '5000000055', 1, 0],
            'etranger'  => ['prestataire', 'Consulting Europe Ltd',    null,         0, 1],
        ];
        $tid = [];
        foreach ($tiers as $k => [$type, $nom, $nif, $valide, $nonResident]) {
            $db->table('tiers')->insert([
                'societe_id' => $societeId, 'type' => $type, 'raison_sociale' => $nom, 'nif' => $nif, 'nif_valide' => $valide,
                'soumis_retenue' => $nonResident, 'non_resident' => $nonResident, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $tid[$k] = (int) $db->insertID();
        }

        // ---- Documents justificatifs -----------------------------------------------------------
        $addDoc = function (string $fichier, array $champs) use ($db, $societeId, $periodeId, $tid, $now) {
            $db->table('documents')->insert([
                'societe_id' => $societeId, 'periode_id' => $periodeId, 'tiers_id' => $tid['fourn'], 'type_document' => 'facture',
                'nom_fichier' => $fichier, 'chemin' => 'writable/uploads/demo/' . $fichier, 'hash_sha256' => hash('sha256', $fichier),
                'statut_extraction' => 'extrait', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = (int) $db->insertID();
            foreach ($champs as $champ => $valeur) {
                $db->table('donnees_extraites')->insert(['document_id' => $id, 'champ' => $champ, 'valeur' => (string) $valeur, 'confiance' => 97.00, 'created_at' => $now]);
            }

            return $id;
        };
        $doc1 = $addDoc('facture_sigma_101.pdf', ['montant_ttc' => 600000, 'date_document' => '2026-10-08', 'nif_emetteur' => '5000000055']);
        $doc2 = $addDoc('facture_sigma_102.pdf', ['montant_ttc' => 960000, 'date_document' => '2026-10-09', 'nif_emetteur' => '5000000055']);

        // ---- Écritures conformes ---------------------------------------------------------------
        $add = function (string $journal, string $piece, string $date, string $libelle, ?int $tiersId, ?int $docId, array $lignes) use ($db, $societeId, $periodeId, $now) {
            $db->table('ecritures')->insert([
                'societe_id' => $societeId, 'periode_id' => $periodeId, 'journal' => $journal, 'numero_piece' => $piece, 'date_ecriture' => $date,
                'libelle' => $libelle, 'tiers_id' => $tiersId, 'document_id' => $docId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = (int) $db->insertID();
            $rows = [];
            foreach ($lignes as [$compteId, $debit, $credit, $codeTva]) {
                $rows[] = ['ecriture_id' => $id, 'compte_id' => $compteId, 'libelle' => $libelle, 'debit' => $debit, 'credit' => $credit, 'code_tva' => $codeTva];
            }
            $db->table('lignes_ecriture')->insertBatch($rows);
        };

        $add('VT', 'VT-001', '2026-10-05', 'Vente à Client Omega', $tid['client'], null, [
            [$compte('411'), 1200000, 0, null], [$compte('706'), 0, 1000000, null], [$compte('4457'), 0, 200000, 'TVA20'],
        ]);
        $add('VT', 'VT-002', '2026-10-06', 'Vente à Client Omega', $tid['client'], null, [
            [$compte('411'), 2400000, 0, null], [$compte('707'), 0, 2000000, null], [$compte('4457'), 0, 400000, 'TVA20'],
        ]);
        $add('AC', 'AC-101', '2026-10-08', 'Achat à Sigma', $tid['fourn'], $doc1, [
            [$compte('601'), 500000, 0, null], [$compte('4456'), 100000, 0, 'TVA20'], [$compte('401'), 0, 600000, null],
        ]);
        $add('AC', 'AC-102', '2026-10-09', 'Achat à Sigma', $tid['fourn'], $doc2, [
            [$compte('607'), 800000, 0, null], [$compte('4456'), 160000, 0, 'TVA20'], [$compte('401'), 0, 960000, null],
        ]);
        $add('AC', 'AC-103', '2026-10-20', 'Honoraires Consulting Europe (retenue 10 %)', $tid['etranger'], null, [
            [$compte('622'), 2000000, 0, null], [$compte('447'), 0, 200000, null], [$compte('401'), 0, 1800000, null],
        ]);
        $add('OD', 'OD-101', '2026-10-12', 'Encaissement client', $tid['client'], null, [
            [$compte('512'), 1200000, 0, null], [$compte('411'), 0, 1200000, null],
        ]);
        $add('PA', 'PAIE-OCT', '2026-10-31', 'Salaires du mois', null, null, [
            [$compte('641'), 1800000, 0, null], [$compte('421'), 0, 1800000, null],
        ]);

        // ---- Employés et bulletins conformes (barème 2026, IRSA recalculé exact) --------------
        $emps = ['rasoa' => ['E001', 'Rasoa', 'Vola'], 'andry' => ['E002', 'Andry', 'Tiana'], 'hanta' => ['E003', 'Hanta', 'Mbola']];
        $eid  = [];
        foreach ($emps as $k => [$matricule, $nom, $prenom]) {
            $db->table('employes')->insert([
                'societe_id' => $societeId, 'matricule' => $matricule, 'nom' => $nom, 'prenom' => $prenom, 'nb_charges' => 0,
                'date_embauche' => '2023-03-01', 'actif' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $eid[$k] = (int) $db->insertID();
        }

        // brut, cnaps_sal, ostie_sal, base, irsa, net, cnaps_pat, ostie_pat, fmfp
        $bulletins = [
            'rasoa' => [800000, 8000, 8000, 784000, 64300, 719700, 104000, 40000, 8000],
            'andry' => [400000, 4000, 4000, 392000,  3000, 389000,  52000, 20000, 4000],
            'hanta' => [600000, 6000, 6000, 588000, 25700, 562300,  78000, 30000, 6000],
        ];
        foreach ($bulletins as $k => $b) {
            $db->table('bulletins_paie')->insert([
                'employe_id' => $eid[$k], 'periode_id' => $periodeId, 'salaire_brut' => $b[0], 'cotisation_cnaps_salarie' => $b[1],
                'cotisation_ostie_salarie' => $b[2], 'base_imposable' => $b[3], 'reduction_charges' => 0, 'irsa_retenu' => $b[4],
                'net_a_payer' => $b[5], 'cotisation_cnaps_patronale' => $b[6], 'cotisation_ostie_patronale' => $b[7], 'fmfp_patronale' => $b[8],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
