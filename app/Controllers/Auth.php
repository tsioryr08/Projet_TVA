<?php

namespace App\Controllers;

use App\Libraries\Workflow\AuditService;
use Config\Database;

/** Connexion et déconnexion. */
class Auth extends BaseController
{
    protected $helpers = ['form', 'url'];

    public function formulaire()
    {
        if (session('utilisateur_id')) {
            return redirect()->to(site_url('/'));
        }

        return view('connexion');
    }

    public function connecter()
    {
        $email = trim((string) $this->request->getPost('email'));
        $mdp   = (string) $this->request->getPost('mot_de_passe');

        $u = Database::connect()->table('utilisateurs u')->select('u.id, u.nom, u.mot_de_passe, r.code AS role_code')
            ->join('roles r', 'r.id = u.role_id')->where(['u.email' => $email, 'u.actif' => 1])->get()->getRowArray();

        if ($u === null || ! password_verify($mdp, $u['mot_de_passe'])) {
            (new AuditService())->journaliser(null, 'connexion_echec', 'utilisateurs', null, null, ['email' => mb_substr($email, 0, 150)]);

            return redirect()->to(site_url('connexion'))->withInput()->with('erreur', 'Identifiants incorrects.');
        }

        $retour = (string) session('retour_apres_connexion');
        session()->regenerate(true);
        session()->set(['utilisateur_id' => (int) $u['id'], 'utilisateur_nom' => $u['nom'], 'utilisateur_role' => $u['role_code']]);
        (new AuditService())->journaliser((int) $u['id'], 'connexion', 'utilisateurs', (int) $u['id'], null, ['role' => $u['role_code']]);

        return redirect()->to($retour !== '' && str_starts_with($retour, '/') && ! str_starts_with($retour, '//') ? site_url(ltrim($retour, '/')) : site_url('/'));
    }

    public function deconnecter()
    {
        if (session('utilisateur_id')) {
            (new AuditService())->journaliser((int) session('utilisateur_id'), 'deconnexion', 'utilisateurs', (int) session('utilisateur_id'), null, null);
        }
        // On vide les données de session et on change d'identifiant (destroy() perdrait le message flash).
        session()->remove(['utilisateur_id', 'utilisateur_nom', 'utilisateur_role', 'ctx_societe', 'ctx_periode', 'retour_apres_connexion']);
        session()->regenerate(true);

        return redirect()->to(site_url('connexion'))->with('info', 'Vous êtes déconnecté.');
    }
}
