<?php

namespace App\Controllers;

use App\Libraries\Anomalies\AnomalieException;
use App\Libraries\Anomalies\AnomalieService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Anomalies en JSON.
 *   GET  /api/anomalies/{societe_id}/{periode_id}?regle=R2&severite=bloquante&statut=ouverte   liste filtrée
 *   GET  /api/anomalies/{id}                                                                   détail : preuves et historique
 *   GET  /api/anomalies/{id}/actions                                                                                actions possibles (boutons)
 *   POST /api/anomalies/{id}/traiter    corps : {"action": "justifier", "commentaire": "..."}
 * L'utilisateur est celui de la session (route protégée par AuthFilter). Les POST exigent le jeton CSRF (en-tête X-CSRF-TOKEN).
 */
class AnomaliesApi extends BaseController
{
    public function lister(int $societeId, int $periodeId): ResponseInterface
    {
        $filtres = array_intersect_key($this->request->getGet() ?? [], array_flip(['regle', 'severite', 'statut']));

        return $this->reponse(fn () => (new AnomalieService())->lister($societeId, $periodeId, $filtres));
    }

    public function detail(int $id): ResponseInterface
    {
        return $this->reponse(fn () => (new AnomalieService())->detail($id));
    }

    public function actions(int $id): ResponseInterface
    {
        return $this->reponse(fn () => (new AnomalieService())->actionsPossibles($id, (int) session('utilisateur_id')));
    }

    public function traiter(int $id): ResponseInterface
    {
        $c = $this->request->getJSON(true) ?? [];

        return $this->reponse(fn () => (new AnomalieService())->traiter($id, (string) ($c['action'] ?? ''), (int) session('utilisateur_id'), $c['commentaire'] ?? null));
    }

    private function reponse(callable $action): ResponseInterface
    {
        try {
            return $this->response->setJSON($action());
        } catch (AnomalieException $e) {
            $code = str_contains($e->getMessage(), 'introuvable') ? 404 : 422;

            return $this->response->setStatusCode($code)->setJSON(['erreur' => $e->getMessage()]);
        }
    }
}
