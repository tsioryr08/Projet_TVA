<?php

namespace App\Controllers;

use App\Libraries\Parametrage\ParametrageException;
use App\Libraries\Parametrage\ParametrageRegles;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Paramétrage des règles en JSON.
 *   GET  /api/regles                       liste des règles et de leurs paramètres
 *   POST /api/regles/{id}/apercu           simule une modification (rien n'est enregistré)
 *   POST /api/regles/{id}/appliquer        applique une modification (motif obligatoire)
 *
 * Corps JSON : {"modifs": {"poids": 3, "active": true, "parametres": {"tolerance_absolue": 200}},
 *               "societe_id": 1, "periode_id": 3, "motif": "..."}
 * L'utilisateur est celui de la session (route protégée par AuthFilter). Les POST exigent le jeton CSRF (en-tête X-CSRF-TOKEN).
 */
class ParametrageApi extends BaseController
{
    public function lister(): ResponseInterface
    {
        return $this->response->setJSON((new ParametrageRegles())->lister());
    }

    public function apercu(int $regleId): ResponseInterface
    {
        $c = $this->request->getJSON(true) ?? [];

        return $this->reponse(fn () => (new ParametrageRegles())->previsualiser($regleId, (array) ($c['modifs'] ?? []), (int) ($c['societe_id'] ?? 0), (int) ($c['periode_id'] ?? 0)));
    }

    public function appliquer(int $regleId): ResponseInterface
    {
        $c = $this->request->getJSON(true) ?? [];

        return $this->reponse(fn () => (new ParametrageRegles())->appliquer(
            $regleId, (array) ($c['modifs'] ?? []), (string) ($c['motif'] ?? ''), (int) session('utilisateur_id'), (int) ($c['societe_id'] ?? 0), (int) ($c['periode_id'] ?? 0)
        ));
    }

    private function reponse(callable $action): ResponseInterface
    {
        try {
            return $this->response->setJSON($action());
        } catch (ParametrageException $e) {
            return $this->response->setStatusCode(422)->setJSON(['erreur' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(404)->setJSON(['erreur' => $e->getMessage()]);
        }
    }
}
