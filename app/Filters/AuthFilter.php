<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Protège les pages et les URL JSON : il faut être connecté.
 * Pages web : redirection vers /connexion. URL /api/... : réponse JSON 401.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('utilisateur_id')) {
            return null;
        }

        // Chemin relatif à l'application (sans index.php, quelle que soit la configuration du serveur).
        $chemin = $request instanceof IncomingRequest ? $request->getPath() : trim($request->getUri()->getPath(), '/');

        if (str_starts_with($chemin, 'api/')) {
            return service('response')->setStatusCode(401)->setJSON(['erreur' => 'Connexion requise.']);
        }

        session()->set('retour_apres_connexion', '/' . ltrim($chemin, '/'));

        return redirect()->to(site_url('connexion'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
