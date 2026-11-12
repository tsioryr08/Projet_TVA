<?php

namespace App\Libraries\Workflow;

use App\Models\DeclarationLigneModel;
use App\Models\DeclarationModel;
use Config\Database;
use DateTime;

/**
 * Génère une déclaration (TVA, IRSA ou RETENUE) à l'état « brouillon » à partir des écritures et des bulletins.
 * Une déclaration existante n'est régénérée que si elle est encore « brouillon » ou « à corriger ».
 */
class DeclarationService
{
    public function generer(int $societeId, int $periodeId, string $type, ?int $utilisateurId): int
    {
        $db      = Database::connect();
        $periode = $db->table('periodes_fiscales')->where(['id' => $periodeId, 'societe_id' => $societeId])->get()->getRowArray();
        if ($periode === null) {
            throw new WorkflowException('Période introuvable pour cette société.');
        }
        if (! in_array($type, ['TVA', 'IRSA', 'RETENUE'], true)) {
            throw new WorkflowException('Type de déclaration inconnu : ' . $type);
        }

        [$lignes, $total] = match ($type) {
            'TVA'     => $this->tva($societeId, $periodeId),
            'IRSA'    => $this->irsa($societeId, $periodeId),
            'RETENUE' => $this->retenue($societeId, $periodeId),
        };

        $modele   = new DeclarationModel();
        $existant = $modele->where(['societe_id' => $societeId, 'periode_id' => $periodeId, 'type' => $type])->first();
        $audit    = new AuditService();

        if ($existant !== null && ! in_array($existant['statut'], ['brouillon', 'a_corriger'], true)) {
            throw new WorkflowException(sprintf('Une déclaration %s est déjà « %s » pour cette période : elle ne peut plus être régénérée.', $type, $existant['statut']));
        }

        $donnees = ['montant_total' => $total, 'date_echeance' => $this->echeance($periode['date_fin'])];

        if ($existant === null) {
            $id = (int) $modele->insert($donnees + [
                'societe_id' => $societeId, 'periode_id' => $periodeId, 'type' => $type, 'statut' => 'brouillon', 'cree_par' => $utilisateurId,
            ]);
            $audit->journaliser($utilisateurId, 'generer_declaration', 'declarations', $id, null, ['type' => $type, 'statut' => 'brouillon', 'montant_total' => (string) $total]);
        } else {
            $id = (int) $existant['id'];
            $modele->update($id, $donnees);
            $db->table('declaration_lignes')->where('declaration_id', $id)->delete();
            $audit->journaliser($utilisateurId, 'regenerer_declaration', 'declarations', $id, ['montant_total' => (string) $existant['montant_total']], ['montant_total' => (string) $total]);
        }

        $lm = new DeclarationLigneModel();
        foreach ($lignes as $l) {
            $lm->insert(['declaration_id' => $id] + $l);
        }

        return $id;
    }

    /** @return array{0:list<array<string,mixed>>,1:float} */
    private function tva(int $societeId, int $periodeId): array
    {
        $db   = Database::connect();
        $base = fn (string $cond) => $db->table('lignes_ecriture l')
            ->select('COALESCE(SUM(l.credit),0) AS credit, COALESCE(SUM(l.debit),0) AS debit')
            ->join('ecritures e', 'e.id = l.ecriture_id')->join('plan_comptable p', 'p.id = l.compte_id')
            ->where(['e.societe_id' => $societeId, 'e.periode_id' => $periodeId])->where($cond, null, false)
            ->get()->getRowArray();

        $ca        = $base("p.numero LIKE '7%'");
        $collectee = $base("p.role_fiscal = 'tva_collectee'");
        $deductible = $base("p.role_fiscal = 'tva_deductible'");

        $caHt   = (float) $ca['credit'] - (float) $ca['debit'];
        $tvaC   = (float) $collectee['credit'] - (float) $collectee['debit'];
        $tvaD   = (float) $deductible['debit'] - (float) $deductible['credit'];
        $aPayer = round($tvaC - $tvaD, 2);

        return [[
            ['code_ligne' => 'TVA_COLLECTEE', 'libelle' => 'TVA collectée sur les ventes (chiffre d\'affaires HT en base)', 'base' => $caHt, 'taux' => null, 'montant' => $tvaC],
            ['code_ligne' => 'TVA_DEDUCTIBLE', 'libelle' => 'TVA déductible sur les achats', 'base' => 0, 'taux' => null, 'montant' => $tvaD],
            ['code_ligne' => 'TVA_A_PAYER', 'libelle' => $aPayer >= 0 ? 'TVA à payer' : 'Crédit de TVA', 'base' => 0, 'taux' => null, 'montant' => $aPayer],
        ], $aPayer];
    }

    /** @return array{0:list<array<string,mixed>>,1:float} */
    private function irsa(int $societeId, int $periodeId): array
    {
        $r = Database::connect()->table('bulletins_paie b')
            ->select('COALESCE(SUM(b.base_imposable),0) AS base, COALESCE(SUM(b.irsa_retenu),0) AS irsa')
            ->join('employes e', 'e.id = b.employe_id')
            ->where(['e.societe_id' => $societeId, 'b.periode_id' => $periodeId])->get()->getRowArray();

        return [[['code_ligne' => 'IRSA_RETENU', 'libelle' => 'IRSA retenu sur les salaires du mois', 'base' => (float) $r['base'], 'taux' => null, 'montant' => (float) $r['irsa']]], (float) $r['irsa']];
    }

    /** @return array{0:list<array<string,mixed>>,1:float} */
    private function retenue(int $societeId, int $periodeId): array
    {
        $db = Database::connect();
        $r  = $db->table('lignes_ecriture l')
            ->select("COALESCE(SUM(CASE WHEN p.numero = '622' THEN l.debit ELSE 0 END),0) AS base, COALESCE(SUM(CASE WHEN p.role_fiscal = 'retenue_source' THEN l.credit - l.debit ELSE 0 END),0) AS retenue", false)
            ->join('ecritures e', 'e.id = l.ecriture_id')->join('plan_comptable p', 'p.id = l.compte_id')
            ->join('tiers t', 't.id = e.tiers_id')
            ->where(['e.societe_id' => $societeId, 'e.periode_id' => $periodeId, 't.non_resident' => 1])->get()->getRowArray();

        return [[['code_ligne' => 'RETENUE_NON_RESIDENT', 'libelle' => 'Retenue sur services payés à des non-résidents', 'base' => (float) $r['base'], 'taux' => 10, 'montant' => (float) $r['retenue']]], (float) $r['retenue']];
    }

    /** Échéance : le jour fixé par la table taux_fiscaux (ECHEANCE_JOUR, 15 par défaut) du mois suivant la fin de période. */
    private function echeance(string $dateFin): string
    {
        $row  = Database::connect()->table('taux_fiscaux')->where('code', 'ECHEANCE_JOUR')->orderBy('date_effet', 'DESC')->get(1)->getRowArray();
        $jour = $row ? (int) $row['valeur'] : 15;
        $d    = (new DateTime($dateFin))->modify('first day of next month');

        return $d->setDate((int) $d->format('Y'), (int) $d->format('m'), $jour)->format('Y-m-d');
    }
}
