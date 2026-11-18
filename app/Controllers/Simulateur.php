<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Score\CalculScore;
use App\Libraries\Score\SimulateurScore;
use InvalidArgumentException;

/**
 * Simulateur « et si je corrige ? » : montre le score projeté de la période si certaines anomalies
 * étaient corrigées ou justifiées. Aucune écriture en base : la simulation est purement un calcul.
 *
 * Schéma : POST /simulateur → redirect → GET /simulateur (avec messages flash). Un rafraîchissement (F5)
 * après la simulation ne renvoie pas de POST, donc pas d'erreur 403 (jeton CSRF ne consomme qu'une seule fois).
 */
class Simulateur extends AppController
{
    /** Page de départ : score actuel et gains individuels. */
    public function index()
    {
        $ctx = $this->contexte();
        if ($ctx['periode'] === null) {
            return $this->vue('simulateur/index', ['titre' => 'Simulateur', 'lignes' => [], 'score' => null, 'choix' => [], 'resultat' => null, 'erreurSimulation' => null]);
        }

        $s = (int) $ctx['societe']['id'];
        $p = (int) $ctx['periode']['id'];

        // Le résultat de la simulation (si un POST vient d'être traité) est transmis en flashdata.
        $resultat = session('resultat_simulation');
        $choix    = session('choix_simulation') ?? [];
        $erreur   = session('erreur_simulation');

        return $this->vue('simulateur/index', [
            'titre'            => 'Simulateur',
            'lignes'           => (new SimulateurScore())->gainsIndividuels($s, $p, $this->utilisateur['role']),
            'score'            => (new CalculScore())->calculer($s, $p, false),
            'choix'            => $choix,
            'resultat'         => $resultat,
            'erreurSimulation' => $erreur,
        ]);
    }

    /** Reçoit choix[ID] = corrigee|justifiee, calcule, puis redirige vers GET /simulateur. */
    public function simuler()
    {
        $ctx = $this->contexte();
        if ($ctx['periode'] === null) {
            return redirect()->to(site_url('simulateur'))->with('erreur', 'Aucune période sélectionnée.');
        }

        // « Ne rien faire » (valeur vide) est ignoré ; toute autre valeur est validée par le service.
        $brut  = $this->request->getPost('choix');
        $choix = [];
        foreach (is_array($brut) ? $brut : [] as $id => $valeur) {
            if (is_string($valeur) && $valeur !== '') {
                $choix[$id] = $valeur;
            }
        }

        try {
            $resultat = (new SimulateurScore())->simuler((int) $ctx['societe']['id'], (int) $ctx['periode']['id'], $choix, $this->utilisateur['role']);

            return redirect()->to(site_url('simulateur'))
                ->with('resultat_simulation', $resultat)
                ->with('choix_simulation', $choix);
        } catch (InvalidArgumentException $e) {
            return redirect()->to(site_url('simulateur'))
                ->with('erreur_simulation', $e->getMessage())
                ->with('choix_simulation', $choix);
        }
    }
}