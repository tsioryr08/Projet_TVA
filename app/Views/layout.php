<?php
/** @var array $ctx */
/** @var array $user */
$uri   = uri_string();
$actif = fn (string $debut) => str_starts_with($uri, $debut) ? ' class="actif"' : '';
$tableau = $uri === '' || str_starts_with($uri, 'tableau');
$peutGerer = in_array($user['role'], ['responsable_fiscal', 'admin'], true);
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($titre ?? 'Moteur fiscal') ?> - Moteur de déclaration fiscale</title>
<link rel="stylesheet" href="<?= base_url('css/theme.css') ?>">
</head>
<body>
<header class="haut">
  <span class="marque">Moteur fiscal</span>
  <nav>
    <a href="<?= site_url('tableau') ?>"<?= $tableau ? ' class="actif"' : '' ?>>Tableau de bord</a>
    <a href="<?= site_url('anomalies') ?>"<?= $actif('anomalies') ?>>Anomalies</a>
    <a href="<?= site_url('simulateur') ?>"<?= $actif('simulateur') ?>>Simulateur</a>
    <a href="<?= site_url('declarations') ?>"<?= $actif('declarations') ?>>Déclarations</a>
    <a href="<?= site_url('bordereaux') ?>"<?= $actif('bordereaux') ?>>Bordereaux</a>
    <a href="<?= site_url('controles') ?>"<?= $actif('controles') ?>>Contrôles</a>
    <?php if ($peutGerer): ?>
    <a href="<?= site_url('regles') ?>"<?= $actif('regles') ?>>Règles</a>
    <a href="<?= site_url('audit') ?>"<?= $actif('audit') ?>>Audit</a>
    <?php endif; ?>
  </nav>
  <?php if (! empty($ctx['societe'])): ?>
  <form class="contexte" method="post" action="<?= site_url('contexte') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="retour" value="/<?= esc($uri, 'attr') ?>">
    <select name="societe" onchange="this.form.submit()" aria-label="Société">
      <?php foreach ($ctx['societes'] as $s): ?>
      <option value="<?= (int) $s['id'] ?>"<?= (int) $s['id'] === (int) $ctx['societe']['id'] ? ' selected' : '' ?>><?= esc($s['raison_sociale']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="periode" onchange="this.form.submit()" aria-label="Période">
      <?php foreach ($ctx['periodes'] as $p): ?>
      <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === (int) ($ctx['periode']['id'] ?? 0) ? ' selected' : '' ?>><?= sprintf('%02d/%d', $p['mois'], $p['annee']) ?> (<?= esc(ui_libelle($p['statut'])) ?>)</option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
  <span class="utilisateur"><?= esc($user['nom']) ?> (<?= esc(ui_libelle($user['role'])) ?>) <a href="<?= site_url('deconnexion') ?>">Déconnexion</a></span>
</header>
<main>
  <?php foreach (['succes', 'erreur', 'info'] as $t): if (session()->getFlashdata($t)): ?>
  <div class="flash <?= $t ?>"><?= esc(session()->getFlashdata($t)) ?></div>
  <?php endif; endforeach; ?>
  <?= $this->renderSection('contenu') ?>
</main>
<?= $this->renderSection('scripts') ?>
</body>
</html>
