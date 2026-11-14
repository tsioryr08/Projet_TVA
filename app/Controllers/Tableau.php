<?php

namespace App\Controllers;

use App\Libraries\Dashboard\DashboardService;

/** Tableau de bord de risque et choix de la société et de la période. */
class Tableau extends AppController
{
    public function index()
    {
        $ctx = $this->contexte();
        if ($ctx['societe'] === null || $ctx['periode'] === null) {
            return $this->vue('tableau', ['d' => null]);
        }

        return $this->vue('tableau', ['d' => (new DashboardService())->donnees((int) $ctx['societe']['id'], (int) $ctx['periode']['id'])]);
    }

    /** Enregistre la société et la période choisies dans le sélecteur du haut de page. */
    public function contexte_choisir()
    {
        $societe = (int) $this->request->getPost('societe');
        $periode = (int) $this->request->getPost('periode');

        if ($societe !== (int) session('ctx_societe')) {
            $periode = 0; // nouvelle société : on reprend sa période la plus récente
        }
        session()->set(['ctx_societe' => $societe, 'ctx_periode' => $periode]);

        $retour = (string) $this->request->getPost('retour');
        $cible  = str_starts_with($retour, '/') && ! str_starts_with($retour, '//') && ! preg_match('#^/anomalies/\d+#', $retour) && ! preg_match('#^/declarations/\d+#', $retour) ? $retour : '/';

        return redirect()->to(site_url(ltrim($cible, '/')));
    }
}
