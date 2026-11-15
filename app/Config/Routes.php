<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ---- Connexion (publique) -----------------------------------------------------------------------
$routes->group('', ['filter' => 'csrf'], static function ($routes) {
    $routes->get('connexion', 'Auth::formulaire');
    $routes->post('connexion', 'Auth::connecter');
});
$routes->get('deconnexion', 'Auth::deconnecter');

// ---- Pages et API protégées : connexion obligatoire, jeton CSRF vérifié sur les POST ------------
$routes->group('', ['filter' => ['csrf', \App\Filters\AuthFilter::class]], static function ($routes) {
    // Interface
    $routes->get('/', 'Tableau::index');
    $routes->get('tableau', 'Tableau::index');
    $routes->post('contexte', 'Tableau::contexte_choisir');

    $routes->get('anomalies', 'Anomalies::liste');
    $routes->post('anomalies/recalculer', 'Anomalies::recalculer');
    $routes->get('anomalies/(:num)', 'Anomalies::detail/$1');
    $routes->post('anomalies/(:num)/traiter', 'Anomalies::traiter/$1');

    $routes->get('declarations', 'Declarations::liste');
    $routes->post('declarations/generer', 'Declarations::generer');
    $routes->get('declarations/(:num)', 'Declarations::detail/$1');
    $routes->post('declarations/(:num)/action', 'Declarations::action/$1');

    $routes->get('regles', 'Regles::liste');
    $routes->post('regles/(:num)', 'Regles::modifier/$1');

    $routes->get('audit', 'Audit::liste');

    // Saisie (Olivier) : bordereaux et historique des contrôles
    $routes->get('bordereaux', 'Bordereaux::liste');
    $routes->post('bordereaux', 'Bordereaux::creer');
    $routes->get('bordereaux/(:num)/fichier', 'Bordereaux::fichier/$1');
    $routes->get('controles', 'Controles::liste');
    $routes->post('controles', 'Controles::creer');

    // API JSON
    $routes->get('api/dashboard/(:num)/(:num)', 'DashboardApi::donnees/$1/$2');
    $routes->get('api/regles', 'ParametrageApi::lister');
    $routes->post('api/regles/(:num)/apercu', 'ParametrageApi::apercu/$1');
    $routes->post('api/regles/(:num)/appliquer', 'ParametrageApi::appliquer/$1');
    $routes->get('api/anomalies/(:num)/(:num)', 'AnomaliesApi::lister/$1/$2');
    $routes->get('api/anomalies/(:num)', 'AnomaliesApi::detail/$1');
    $routes->get('api/anomalies/(:num)/actions', 'AnomaliesApi::actions/$1');
    $routes->post('api/anomalies/(:num)/traiter', 'AnomaliesApi::traiter/$1');
});
