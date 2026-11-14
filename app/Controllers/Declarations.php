<?php

namespace App\Controllers;

use App\Libraries\Score\CalculScore;
use App\Libraries\Workflow\DeclarationService;
use App\Libraries\Workflow\WorkflowException;
use App\Libraries\Workflow\WorkflowService;
use Config\Database;

/** Déclarations (TVA, IRSA, retenues) et leur workflow de validation. */
class Declarations extends AppController
{
    public function liste()
    {
        $ctx = $this->contexte();
        $declarations = $ctx['societe'] === null ? [] : Database::connect()->table('declarations d')
            ->select('d.*, p.mois, p.annee')->join('periodes_fiscales p', 'p.id = d.periode_id')
            ->where('d.societe_id', $ctx['societe']['id'])->orderBy('p.annee', 'DESC')->orderBy('p.mois', 'DESC')->orderBy('d.type')->get()->getResultArray();

        return $this->vue('declarations/liste', ['declarations' => $declarations]);
    }

    public function generer()
    {
        if (! in_array($this->utilisateur['role'], ['comptable', 'admin'], true)) {
            return redirect()->to(site_url('declarations'))->with('erreur', 'La génération d\'une déclaration est réservée au comptable.');
        }
        $ctx = $this->contexte();
        try {
            $id = (new DeclarationService())->generer((int) $ctx['societe']['id'], (int) $ctx['periode']['id'], (string) $this->request->getPost('type'), $this->utilisateur['id']);
        } catch (WorkflowException $e) {
            return redirect()->to(site_url('declarations'))->with('erreur', $e->getMessage());
        }

        return redirect()->to(site_url('declarations/' . $id))->with('succes', 'Déclaration générée en brouillon.');
    }

    public function detail(int $id)
    {
        $db = Database::connect();
        $d  = $db->table('declarations d')->select('d.*, p.mois, p.annee')->join('periodes_fiscales p', 'p.id = d.periode_id')->where('d.id', $id)->get()->getRowArray();
        if ($d === null) {
            return redirect()->to(site_url('declarations'))->with('erreur', 'Déclaration introuvable.');
        }

        $wf = new WorkflowService();

        return $this->vue('declarations/detail', [
            'd'           => $d,
            'lignes'      => $db->table('declaration_lignes')->where('declaration_id', $id)->orderBy('id')->get()->getResultArray(),
            'validations' => $db->table('validations v')->select('v.*, u.nom')->join('utilisateurs u', 'u.id = v.utilisateur_id')->where('v.declaration_id', $id)->orderBy('v.id')->get()->getResultArray(),
            'actions'     => $wf->actionsPossibles($id, $this->utilisateur['id']),
            'bloquantes'  => $wf->anomaliesBloquantes($d),
            'score'       => (new CalculScore())->calculer((int) $d['societe_id'], (int) $d['periode_id'], false),
        ]);
    }

    public function action(int $id)
    {
        try {
            $r = (new WorkflowService())->transitionner(
                $id, (string) $this->request->getPost('action'), $this->utilisateur['id'],
                (string) $this->request->getPost('commentaire'), ['reference_virement' => (string) $this->request->getPost('reference_virement')]
            );
        } catch (WorkflowException $e) {
            return redirect()->to(site_url('declarations/' . $id))->with('erreur', $e->getMessage());
        }

        return redirect()->to(site_url('declarations/' . $id))->with('succes', sprintf(
            'Action « %s » effectuée : %s → %s.%s', $r['action'], ui_libelle($r['de']), ui_libelle($r['vers']), $r['score'] !== null ? ' Score au moment de l\'action : ' . ui_nombre($r['score']) . ' / 100.' : ''
        ));
    }
}
