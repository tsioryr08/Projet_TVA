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
<style>
:root{--marine:#1f3a5f;--fond:#f3f5f9;--bord:#dbe1ea;--texte:#1d2733;--doux:#5b6878;--vert:#2e7d32;--ambre:#b26a00;--orange:#d84315;--rouge:#b71c1c;--bleu:#1565c0;--gris:#6b7684}
*{box-sizing:border-box}
body{margin:0;font:15px/1.5 -apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:var(--texte);background:var(--fond)}
header.haut{background:var(--marine);color:#fff;display:flex;flex-wrap:wrap;align-items:center;gap:8px 20px;padding:10px 22px}
header.haut .marque{font-weight:700;letter-spacing:.2px;margin-right:8px}
header.haut nav{display:flex;flex-wrap:wrap;gap:4px}
header.haut nav a{color:#cfdcf0;text-decoration:none;padding:6px 12px;border-radius:6px}
header.haut nav a:hover{background:rgba(255,255,255,.12)}
header.haut nav a.actif{background:#fff;color:var(--marine);font-weight:600}
.contexte{margin-left:auto;display:flex;gap:6px;align-items:center}
.contexte select{padding:5px 8px;border-radius:6px;border:0;font-size:14px;max-width:230px}
.utilisateur{font-size:13px;color:#cfdcf0;display:flex;gap:10px;align-items:center}
.utilisateur a{color:#fff}
main{max-width:1180px;margin:0 auto;padding:22px}
h1{font-size:23px;margin:0 0 4px;color:var(--marine)}
h2{font-size:17px;margin:0 0 12px;color:var(--marine)}
.sous{color:var(--doux);margin:0 0 18px}
.carte{background:#fff;border:1px solid var(--bord);border-radius:10px;padding:18px;margin-bottom:18px}
.grille{display:grid;gap:18px}
.g2{grid-template-columns:repeat(auto-fit,minmax(340px,1fr))}
.g4{grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}
.kpi{background:#fff;border:1px solid var(--bord);border-radius:10px;padding:14px 16px}
.kpi .v{font-size:25px;font-weight:700;color:var(--marine)}
.kpi .l{color:var(--doux);font-size:13px}
.kpi.alerte .v{color:var(--rouge)}
table{width:100%;border-collapse:collapse;font-size:14px}
th{text-align:left;color:var(--doux);font-weight:600;border-bottom:2px solid var(--bord);padding:8px 10px;white-space:nowrap}
td{border-bottom:1px solid var(--bord);padding:8px 10px;vertical-align:top}
tr:hover td{background:#fafbfd}
td.num,th.num{text-align:right;white-space:nowrap}
a{color:var(--bleu)}
.badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.gravite-info,.statut-ignoree,.decl-brouillon,.decl-archivee,.periode-cloturee{background:var(--gris)}
.gravite-moyenne,.statut-justifiee,.decl-a_valider,.periode-declaree{background:var(--bleu)}
.gravite-elevee,.niveau-eleve,.decl-a_corriger{background:var(--orange)}
.gravite-bloquante,.niveau-critique,.statut-ouverte{background:var(--rouge)}
.statut-corrigee,.niveau-faible,.decl-validee,.decl-deposee,.periode-ouverte{background:var(--vert)}
.niveau-moyen{background:var(--ambre)}
.flash{padding:11px 15px;border-radius:8px;margin-bottom:16px;border:1px solid}
.flash.succes{background:#e8f5e9;border-color:#a5d6a7;color:#1b5e20}
.flash.erreur{background:#ffebee;border-color:#ef9a9a;color:#b71c1c}
.flash.info{background:#e3f2fd;border-color:#90caf9;color:#0d47a1}
.bandeau-bloque{background:#ffebee;border:1px solid #ef9a9a;color:#b71c1c;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-weight:600}
.bandeau-ok{background:#e8f5e9;border:1px solid #a5d6a7;color:#1b5e20;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-weight:600}
label{display:block;font-size:13px;color:var(--doux);margin:8px 0 3px}
input[type=text],input[type=email],input[type=password],input[type=number],select,textarea{width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:6px;font:inherit;background:#fff}
textarea{min-height:64px;resize:vertical}
.btn{display:inline-block;padding:8px 16px;border:0;border-radius:6px;background:var(--marine);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.btn:hover{background:#2a4d7c}
.btn.sec{background:#fff;color:var(--marine);border:1px solid var(--marine)}
.btn.danger{background:var(--rouge)}
.btn[disabled]{background:#c9d1dc;color:#7c8794;cursor:not-allowed;border:0}
.ligne-actions{display:flex;flex-wrap:wrap;gap:12px}
.action-bloc{flex:1 1 260px;border:1px solid var(--bord);border-radius:8px;padding:12px;background:#fbfcfe}
.action-bloc h3{margin:0 0 4px;font-size:15px;text-transform:capitalize}
.raison{font-size:12.5px;color:var(--doux);margin-top:6px}
.filtres{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:14px}
.filtres>div{min-width:140px}
.cols{columns:2;column-gap:30px}
.mini{font-size:13px;color:var(--doux)}
.jauge{position:relative;max-width:300px;margin:0 auto}
.jauge .centre{position:absolute;left:0;right:0;bottom:6px;text-align:center}
.jauge .centre b{font-size:38px;display:block;line-height:1}
.chaleur td.c{text-align:center;font-weight:600;min-width:62px}
code,.mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px}
.connexion{max-width:400px;margin:9vh auto}
@media(max-width:700px){main{padding:14px}.cols{columns:1}}
</style>
</head>
<body>
<header class="haut">
  <span class="marque">Moteur fiscal</span>
  <nav>
    <a href="<?= site_url('tableau') ?>"<?= $tableau ? ' class="actif"' : '' ?>>Tableau de bord</a>
    <a href="<?= site_url('anomalies') ?>"<?= $actif('anomalies') ?>>Anomalies</a>
    <a href="<?= site_url('declarations') ?>"<?= $actif('declarations') ?>>Déclarations</a>
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
