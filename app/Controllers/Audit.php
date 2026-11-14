<?php

namespace App\Controllers;

use App\Libraries\Workflow\AuditService;
use Config\Database;

/** Consultation du journal d'audit et vérification de sa chaîne de hash. */
class Audit extends AppController
{
    public function liste()
    {
        if ($refus = $this->exiger(['responsable_fiscal', 'admin'])) {
            return $refus;
        }

        $lignes = Database::connect()->table('journal_audit j')->select('j.id, j.created_at, j.action, j.table_cible, j.enregistrement_id, j.donnees_apres, j.hash_courant, u.nom')
            ->join('utilisateurs u', 'u.id = j.utilisateur_id', 'left')->orderBy('j.id', 'DESC')->get(150)->getResultArray();

        return $this->vue('audit/liste', ['lignes' => $lignes, 'chaine' => (new AuditService())->verifierChaine()]);
    }
}
