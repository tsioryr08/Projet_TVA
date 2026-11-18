<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion - Moteur de déclaration fiscale</title>
<link rel="stylesheet" href="<?= base_url('css/theme.css') ?>">
<style>
.connexion .carte { padding: 32px; }
.connexion h1 { text-align: center; margin-bottom: 4px; }
.connexion .sous { text-align: center; margin-bottom: 28px; }
.connexion .aide { margin-top: 24px; text-align: center; font-size: 0.85rem; color: var(--texte-secondaire); padding-top: 16px; border-top: 1px solid var(--bord); }
.connexion .aide b { color: var(--texte); }
.connexion button.btn { width: 100%; margin-top: 8px; }
</style>
</head>
<body>
<div class="connexion">
  <div class="carte">
    <h1>Moteur de déclaration fiscale</h1>
    <p class="sous">TVA, IRSA et retenues : connexion</p>
    <?php foreach (['erreur', 'info'] as $t): if (session()->getFlashdata($t)): ?>
    <div class="flash <?= $t ?>"><?= esc(session()->getFlashdata($t)) ?></div>
    <?php endif; endforeach; ?>
    <form method="post" action="<?= site_url('connexion') ?>">
      <?= csrf_field() ?>
      <label for="email">Adresse e-mail</label>
      <input id="email" type="email" name="email" value="<?= esc(old('email') ?? '', 'attr') ?>" required autofocus>
      <label for="mdp">Mot de passe</label>
      <input id="mdp" type="password" name="mot_de_passe" required>
      <button type="submit" class="btn">Se connecter</button>
    </form>
    <div class="aide">Comptes de démonstration (mot de passe <b>Demo@2026</b>) : comptable@demo.mg, fiscal@demo.mg, admin@demo.mg</div>
  </div>
</div>
</body>
</html>