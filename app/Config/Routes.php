<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('api/dashboard/(:num)/(:num)', 'DashboardApi::donnees/$1/$2');
$routes->get('api/regles', 'ParametrageApi::lister');
$routes->post('api/regles/(:num)/apercu', 'ParametrageApi::apercu/$1');
$routes->post('api/regles/(:num)/appliquer', 'ParametrageApi::appliquer/$1');