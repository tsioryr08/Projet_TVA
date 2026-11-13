<?php

namespace App\Controllers;

use App\Libraries\Dashboard\DashboardService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Point d'accès JSON du dashboard : GET /api/dashboard/{societe_id}/{periode_id}
 * À protéger par l'authentification d'Olivier (filtre de connexion) avant la mise en ligne.
 */
class DashboardApi extends BaseController
{
    public function donnees(int $societeId, int $periodeId): ResponseInterface
    {
        try {
            return $this->response->setJSON((new DashboardService())->donnees($societeId, $periodeId));
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(404)->setJSON(['erreur' => $e->getMessage()]);
        }
    }
}
