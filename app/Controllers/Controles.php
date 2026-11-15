<?php

namespace App\Controllers;

use App\Libraries\Saisie\ControleHistoriqueService;
use App\Libraries\Saisie\SaisieException;
use App\Libraries\Score\CalculScore;

/**
 * Historique des contrôles fiscaux de la société. Il alimente le facteur historique du score :
 * après une saisie, on recalcule le score et on affiche l'effet.
 */
class Controles extends AppController
{
    private const ROLES_SAISIE = ['responsable_fiscal', 'admin'];

    public function liste()
    {
        $ctx = $this->contexte();
        if ($ctx['societe'] === null) {
            return $this->vue('controles/liste', ['controles' => [], 'regles' => [], 'peutSaisir' => false]);
        }
        $service = new ControleHistoriqueService();

        return $this->vue('controles/liste', [
            'controles'  => $service->lister((int) $ctx['societe']['id']),
            'regles'     => $service->regles(),
            'peutSaisir' => in_array($this->utilisateur['role'], self::ROLES_SAISIE, true),
        ]);
    }

    public function creer()
    {
        if ($refus = $this->exiger(self::ROLES_SAISIE)) {
            return $refus;
        }
        $ctx = $this->contexte();
        if ($ctx['societe'] === null) {
            return redirect()->to(site_url('controles'))->with('erreur', 'Aucune société sélectionnée.');
        }
        $s     = (int) $ctx['societe']['id'];
        $score = new CalculScore();
        $avant = $ctx['periode'] === null ? null : $score->calculer($s, (int) $ctx['periode']['id'], false);
        try {
            (new ControleHistoriqueService())->creer($s, $this->request->getPost() ?? [], $this->utilisateur['id']);
        } catch (SaisieException $e) {
            return redirect()->to(site_url('controles'))->withInput()->with('erreur', $e->getMessage());
        }
        if ($avant === null) {
            return redirect()->to(site_url('controles'))->with('succes', 'Contrôle enregistré.');
        }
        $apres = $score->calculer($s, (int) $ctx['periode']['id'], true);

        return redirect()->to(site_url('controles'))->with('succes', sprintf(
            'Contrôle enregistré. Score de la période %02d/%d : %s (%s) → %s (%s).',
            $ctx['periode']['mois'], $ctx['periode']['annee'], ui_nombre($avant['score']), ui_libelle($avant['niveau']), ui_nombre($apres['score']), ui_libelle($apres['niveau'])
        ));
    }
}
