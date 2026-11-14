<?php

namespace App\Controllers;

use App\Libraries\Parametrage\ParametrageException;
use App\Libraries\Parametrage\ParametrageRegles;

/** Paramétrage des règles : poids, gravité, activation et seuils, avec aperçu du score. */
class Regles extends AppController
{
    public function liste()
    {
        if ($refus = $this->exiger(['responsable_fiscal', 'admin'])) {
            return $refus;
        }

        return $this->vue('regles/liste', ['regles' => (new ParametrageRegles())->lister()]);
    }

    public function modifier(int $id)
    {
        if ($refus = $this->exiger(['responsable_fiscal', 'admin'])) {
            return $refus;
        }
        $ctx = $this->contexte();
        $service = new ParametrageRegles();

        $modifs = [
            'poids'  => (string) $this->request->getPost('poids'),
            'gravite' => (string) $this->request->getPost('gravite'),
            'active' => $this->request->getPost('active') === '1' ? '1' : '0',
        ];
        $params = $this->request->getPost('parametres');
        if (is_array($params) && $params !== []) {
            $modifs['parametres'] = $params;
        }

        try {
            if ($this->request->getPost('mode') === 'apercu') {
                $r = $service->previsualiser($id, $modifs, (int) $ctx['societe']['id'], (int) $ctx['periode']['id']);
            } else {
                $r = $service->appliquer($id, $modifs, (string) $this->request->getPost('motif'), $this->utilisateur['id'], (int) $ctx['societe']['id'], (int) $ctx['periode']['id']);
            }
        } catch (ParametrageException $e) {
            return redirect()->to(site_url('regles') . '#regle-' . $id)->with('erreur', $e->getMessage());
        }

        return redirect()->to(site_url('regles') . '#regle-' . $id)->with('resultat_regle', $r);
    }
}
