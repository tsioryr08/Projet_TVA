<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Psr\Log\LoggerInterface;

/**
 * Base des contrôleurs de l'interface : utilisateur connecté, contexte (société et période choisies),
 * rendu des vues, messages. Aucune logique métier ici : elle est dans app/Libraries.
 */
abstract class AppController extends BaseController
{
    protected $helpers = ['ui', 'form', 'url'];

    /** @var array{id:int,nom:string,role:string} */
    protected array $utilisateur = ['id' => 0, 'nom' => '', 'role' => ''];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->utilisateur = [
            'id' => (int) session('utilisateur_id'), 'nom' => (string) session('utilisateur_nom'), 'role' => (string) session('utilisateur_role'),
        ];
    }

    /**
     * Société et période en cours (mémorisées en session), avec les listes pour le sélecteur.
     *
     * @return array{societe:?array<string,mixed>,periode:?array<string,mixed>,societes:list<array<string,mixed>>,periodes:list<array<string,mixed>>}
     */
    protected function contexte(): array
    {
        $db       = Database::connect();
        $societes = $db->table('societes')->orderBy('id')->get()->getResultArray();
        $societe  = null;
        foreach ($societes as $s) {
            if ((int) $s['id'] === (int) session('ctx_societe')) {
                $societe = $s;
            }
        }
        $societe ??= $societes[0] ?? null;

        $periodes = $societe === null ? [] : $db->table('periodes_fiscales')->where('societe_id', $societe['id'])->orderBy('annee', 'DESC')->orderBy('mois', 'DESC')->get()->getResultArray();
        $periode  = null;
        foreach ($periodes as $p) {
            if ((int) $p['id'] === (int) session('ctx_periode')) {
                $periode = $p;
            }
        }
        $periode ??= $periodes[0] ?? null;

        return ['societe' => $societe, 'periode' => $periode, 'societes' => $societes, 'periodes' => $periodes];
    }

    /** @param array<string,mixed> $donnees */
    protected function vue(string $nom, array $donnees = []): string
    {
        return view($nom, $donnees + ['ctx' => $this->contexte(), 'user' => $this->utilisateur]);
    }

    /** Refuse l'accès si le rôle n'est pas dans la liste (renvoie une redirection avec message), sinon null. */
    protected function exiger(array $roles): ?\CodeIgniter\HTTP\RedirectResponse
    {
        if (in_array($this->utilisateur['role'], $roles, true)) {
            return null;
        }

        return redirect()->to(site_url('/'))->with('erreur', 'Cette page est réservée à : ' . implode(', ', array_map('ui_libelle', $roles)) . '.');
    }
}
