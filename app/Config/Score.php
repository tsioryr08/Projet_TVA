<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Paramètres du score de risque. À valider ensemble (Tsiory et Olivier) puis à ne plus changer avant la démo.
 *
 * Formule : score = min(100, multiplicateur x somme des points + majoration historique)
 *   points d'une anomalie = points de sa gravité x poids de la règle x coefficient de son statut
 */
class Score extends BaseConfig
{
    /** Multiplicateur appliqué à la somme des points (5 sature très vite, 3 laisse plus de nuances). */
    public float $multiplicateur = 3.0;

    /** Majoration si la société a déjà eu un redressement sur une règle qui déclenche une anomalie aujourd'hui. */
    public float $majorationHistorique = 10.0;

    /** Points par gravité. */
    public array $points = ['info' => 0, 'moyenne' => 1, 'elevee' => 2, 'bloquante' => 3];

    /** Coefficient par statut : une anomalie justifiée pèse moitié moins, une corrigée ne pèse plus. */
    public array $coefficientStatut = ['ouverte' => 1.0, 'justifiee' => 0.5, 'corrigee' => 0.0, 'ignoree' => 0.0];

    /** Seuils des niveaux : en dessous de seuilMoyen = faible, etc. */
    public int $seuilMoyen    = 25;
    public int $seuilEleve    = 50;
    public int $seuilCritique = 75;
}
