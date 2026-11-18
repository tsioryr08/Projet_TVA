<?php

namespace App\Controllers;

use App\Libraries\Anomalies\AnomalieException;
use App\Libraries\Anomalies\RectificationService;

/** Rectification, depuis une anomalie, des données qui l'ont déclenchée (écriture comptable ou bulletin). Logique : RectificationService. */
class Rectifier extends AppController
{
    private const ROLES = ['comptable', 'admin'];

    public function formulaire(int $anomalieId)
    {
        if ($refus = $this->exiger(self::ROLES)) {
            return $refus;
        }
        try {
            $c = (new RectificationService())->cible($anomalieId);
        } catch (AnomalieException $e) {
            return redirect()->to(site_url('anomalies'))->with('erreur', $e->getMessage());
        }
        if ($c['type'] === null) {
            return redirect()->to(site_url('anomalies/' . $anomalieId))->with('erreur', 'Aucune donnée à rectifier n\'est rattachée à cette anomalie.');
        }

        return $this->vue('anomalies/rectifier', $c);
    }

    public function enregistrer(int $anomalieId)
    {
        if ($refus = $this->exiger(self::ROLES)) {
            return $refus;
        }
        $service = new RectificationService();
        try {
            $type = $service->cible($anomalieId)['type'];
            $msg  = $type === 'bulletin'
                ? $service->rectifierBulletin($anomalieId, $this->request->getPost() ?? [], $this->utilisateur['id'])
                : $service->rectifierEcriture($anomalieId, $this->request->getPost() ?? [], $this->utilisateur['id']);
        } catch (AnomalieException $e) {
            return redirect()->to(site_url('anomalies/' . $anomalieId . '/rectifier'))->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->to(site_url('anomalies/' . $anomalieId))->with('succes', $msg . ' Cliquez maintenant sur « Marquer corrigée » : le moteur vérifie que l\'anomalie a disparu.');
    }

    public function supprimer(int $anomalieId)
    {
        if ($refus = $this->exiger(self::ROLES)) {
            return $refus;
        }
        try {
            $msg = (new RectificationService())->supprimerDoublon($anomalieId, (string) $this->request->getPost('motif'), $this->utilisateur['id']);
        } catch (AnomalieException $e) {
            return redirect()->to(site_url('anomalies/' . $anomalieId . '/rectifier'))->with('erreur', $e->getMessage());
        }

        return redirect()->to(site_url('anomalies/' . $anomalieId))->with('succes', $msg . ' Cliquez maintenant sur « Marquer corrigée ».');
    }
}