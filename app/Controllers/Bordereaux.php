<?php

namespace App\Controllers;

use App\Libraries\Saisie\BordereauService;
use App\Libraries\Saisie\SaisieException;

/** Saisie assistée des bordereaux : formulaire + PDF joint. Aucune logique métier ici (voir BordereauService). */
class Bordereaux extends AppController
{
    private const ROLES_SAISIE = ['comptable', 'admin'];

    public function liste()
    {
        $ctx = $this->contexte();
        if ($ctx['societe'] === null) {
            return $this->vue('bordereaux/liste', ['documents' => [], 'tiers' => [], 'peutSaisir' => false]);
        }
        $service = new BordereauService();

        return $this->vue('bordereaux/liste', [
            'documents'  => $service->lister((int) $ctx['societe']['id'], $ctx['periode'] === null ? null : (int) $ctx['periode']['id']),
            'tiers'      => $service->tiers((int) $ctx['societe']['id']),
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
            return redirect()->to(site_url('bordereaux'))->with('erreur', 'Aucune société sélectionnée.');
        }
        try {
            $r = (new BordereauService())->creer(
                (int) $ctx['societe']['id'],
                $ctx['periode'] === null ? null : (int) $ctx['periode']['id'],
                $this->request->getPost() ?? [],
                $this->request->getFile('fichier'),
                $this->utilisateur['id']
            );
        } catch (SaisieException $e) {
            return redirect()->to(site_url('bordereaux'))->withInput()->with('erreur', $e->getMessage());
        }
        $redirection = redirect()->to(site_url('bordereaux'))->with('succes', 'Bordereau enregistré (document n° ' . $r['id'] . ').');
        if ($r['avertissement'] !== null) {
            $redirection->with('info', $r['avertissement']);
        }

        return $redirection;
    }

    /** Affiche le PDF joint (stocké hors de public/, donc servi par ici, derrière la connexion). */
    public function fichier(int $id)
    {
        $f = (new BordereauService())->fichier($id);
        if ($f === null) {
            return redirect()->to(site_url('bordereaux'))->with('erreur', 'Fichier introuvable (les documents de démonstration n\'ont pas de PDF).');
        }

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="document-' . $id . '.pdf"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($f['chemin']));
    }
}
