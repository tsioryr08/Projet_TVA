<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<?php if ($d === null): ?>
  <div class="carte">Aucune société ou période en base. Lancez <code>php spark db:seed DatabaseSeeder</code>.</div>
<?php else:
  $sc  = $d['score'];
  $k   = $d['kpi'];
  $det = $sc['detail'];
  /* Palette sémantique unifiée : sauge (faible), sable (moyen), ocre (élevé), terracotta/brique (critique) */
  $couleurs = ['faible' => '#7a9e7e', 'moyen' => '#e8dcc8', 'eleve' => '#c48a3a', 'critique' => '#c45a3a'];
?>
<h1>Tableau de bord de risque</h1>
<p class="sous"><?= esc($d['societe']['raison_sociale']) ?> - période <?= esc($d['periode']['libelle']) ?> (<?= esc(ui_libelle($d['periode']['statut'])) ?>)</p>

<?php if ($k['validation_bloquee']): ?>
  <div class="bandeau-bloque">Validation bloquée : <?= (int) $k['nb_bloquantes_ouvertes'] ?> anomalie(s) bloquante(s) ouverte(s). <a href="<?= site_url('anomalies?severite=bloquante&statut=ouverte') ?>">Les voir</a></div>
<?php else: ?>
  <div class="bandeau-ok">Aucune anomalie bloquante ouverte : la validation est possible.</div>
<?php endif; ?>

<div class="grille g4" style="margin-bottom:18px">
  <div class="kpi<?= $k['nb_ouvertes'] > 0 ? ' alerte' : '' ?>"><div class="v"><?= (int) $k['nb_ouvertes'] ?> / <?= (int) $k['nb_anomalies'] ?></div><div class="l">anomalies ouvertes</div></div>
  <div class="kpi<?= $k['nb_bloquantes_ouvertes'] > 0 ? ' alerte' : '' ?>"><div class="v"><?= (int) $k['nb_bloquantes_ouvertes'] ?></div><div class="l">anomalies bloquantes ouvertes</div></div>
  <div class="kpi"><div class="v"><?= esc(ui_ar($k['montant_concerne_ouvert'])) ?></div><div class="l">montant à risque encore ouvert</div></div>
  <div class="kpi"><div class="v"><?= (int) $k['taux_traitement'] ?> %</div><div class="l">taux de traitement</div></div>
</div>

<div class="grille g2">
  <div class="carte">
    <h2>Score de risque</h2>
    <div class="jauge"><canvas id="jauge" height="170"></canvas>
      <div class="centre"><b style="color:<?= $couleurs[$sc['niveau']] ?? '#5d4e43' ?>"><?= esc(ui_nombre($sc['valeur'])) ?></b><?= ui_badge('niveau', $sc['niveau']) ?></div>
    </div>
    <?php if ($sc['variation'] !== null): ?>
      <p class="mini" style="text-align:center;margin:10px 0 0">Par rapport à <?= esc($sc['precedent']['periode']) ?> : <b><?= $sc['variation'] > 0 ? '+' : '' ?><?= esc(ui_nombre($sc['variation'])) ?></b> point(s)</p>
    <?php endif; ?>
  </div>
  <div class="carte"><h2>Évolution du score</h2><canvas id="evolution" height="190"></canvas></div>
</div>

<div class="carte">
  <h2>Pourquoi <?= esc(ui_nombre($sc['valeur'])) ?> / 100 ?</h2>
  <p class="mini">Points d'une anomalie = points de sa gravité x poids de la règle x coefficient de statut. Score = multiplicateur x somme des points + majoration historique, plafonné à 100.</p>
  <table>
    <thead><tr><th>Règle</th><th>Contrôle</th><th class="num">Anomalies</th><th class="num">Poids</th><th class="num">Points</th><th class="num">Contribution</th></tr></thead>
    <tbody>
    <?php foreach ($det['regles'] ?? [] as $r): ?>
      <tr><td><b><?= esc($r['code']) ?></b></td><td><?= esc($r['intitule']) ?></td><td class="num"><?= (int) $r['nb_anomalies'] ?></td><td class="num"><?= esc(ui_nombre($r['poids'])) ?></td><td class="num"><?= esc(ui_nombre($r['points'])) ?></td><td class="num"><b><?= esc(ui_nombre($r['contribution'])) ?></b></td></tr>
    <?php endforeach; ?>
      <tr><td colspan="4"><b>Somme des points</b> (x <?= esc(ui_nombre($det['multiplicateur'] ?? 0)) ?>)</td><td class="num"><?= esc(ui_nombre($det['somme_points'] ?? 0)) ?></td><td class="num"><b><?= esc(ui_nombre(($det['somme_points'] ?? 0) * ($det['multiplicateur'] ?? 0))) ?></b></td></tr>
      <?php foreach ($det['historique']['declencheurs'] ?? [] as $h): ?>
      <tr><td colspan="5">Historique : redressement du <?= esc(ui_date($h['date_controle'])) ?> sur <?= esc($h['type_impot']) ?> (<?= esc(ui_ar($h['redressement_montant'])) ?>), règle <?= esc($h['regle']) ?></td><td class="num"><b>+ <?= esc(ui_nombre($det['historique']['majoration'])) ?></b></td></tr>
      <?php endforeach; ?>
      <tr><td colspan="5"><b>Score</b><?= ! empty($det['plafonne']) ? ' (brut ' . esc(ui_nombre($det['score_brut'])) . ', plafonné à 100)' : '' ?></td><td class="num"><b><?= esc(ui_nombre($sc['valeur'])) ?> / 100</b></td></tr>
    </tbody>
  </table>
</div>

<div class="grille g2">
  <div class="carte"><h2>Anomalies par règle</h2><canvas id="regles" height="200"></canvas></div>
  <div class="carte"><h2>Anomalies par gravité</h2><div style="max-width:300px;margin:0 auto"><canvas id="gravite" height="200"></canvas></div></div>
</div>

<div class="carte">
  <h2>Anomalies par règle et par période</h2>
  <?php $h = $d['graphiques']['heatmap']; $max = max(1, ...array_merge([1], ...$h['valeurs'])); ?>
  <table class="chaleur">
    <thead><tr><th>Règle</th><?php foreach ($h['periodes'] as $p): ?><th class="num"><?= esc($p) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($h['regles'] as $i => $code): ?>
      <tr><td><b><?= esc($code) ?></b></td>
      <?php foreach ($h['valeurs'][$i] as $v): $a = $v === 0 ? 0 : 0.18 + 0.72 * ($v / $max); ?>
        <td class="c" style="background:rgba(196,90,58,<?= round($a, 2) ?>);color:<?= $a > 0.5 ? '#fff' : 'inherit' ?>"><?= (int) $v ?></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="grille g2">
  <div class="carte">
    <h2>Plus grosses anomalies ouvertes</h2>
    <?php if ($d['top_anomalies'] === []): ?><p class="mini">Aucune anomalie ouverte.</p><?php else: ?>
    <table><tbody>
      <?php foreach ($d['top_anomalies'] as $a): ?>
      <tr><td><a href="<?= site_url('anomalies/' . $a['id']) ?>"><b><?= esc($a['regle']) ?></b></a> <?= ui_badge('gravite', $a['severite']) ?><br><span class="mini"><?= esc(mb_strimwidth($a['message'], 0, 95, '...')) ?></span></td><td class="num"><b><?= esc(ui_ar($a['montant'])) ?></b></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>
  <div class="carte">
    <h2>Échéances des déclarations</h2>
    <?php if ($d['echeances'] === []): ?><p class="mini">Aucune déclaration générée. <a href="<?= site_url('declarations') ?>">Aller aux déclarations</a></p><?php else: ?>
    <table><tbody>
      <?php foreach ($d['echeances'] as $e): $lib = ['en_retard' => 'en retard', 'alerte' => 'à échéance proche', 'ok' => 'à venir', 'termine' => 'terminée'][$e['etat']]; $cls = ['en_retard' => 'gravite-bloquante', 'alerte' => 'gravite-elevee', 'ok' => 'statut-corrigee', 'termine' => 'gravite-info'][$e['etat']]; ?>
      <tr><td><a href="<?= site_url('declarations/' . $e['declaration_id']) ?>"><b><?= esc($e['type']) ?> <?= esc($e['periode']) ?></b></a><br><span class="mini"><?= esc(ui_libelle($e['statut'])) ?> - échéance <?= esc(ui_date($e['date_echeance'])) ?><?= $e['jours_restants'] !== null && $e['etat'] !== 'termine' ? ' (' . ($e['jours_restants'] >= 0 ? 'dans ' . $e['jours_restants'] . ' j' : 'dépassée de ' . abs($e['jours_restants']) . ' j') . ')' : '' ?></span></td><td class="num"><span class="badge <?= $cls ?>"><?= $lib ?></span></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

<?php if ($d !== null): ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/chart.umd.min.js') ?>"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') {
    document.querySelectorAll('canvas').forEach(function (c) { c.insertAdjacentHTML('afterend', '<p class="mini">Graphique indisponible : Chart.js n\'a pas pu être chargé (connexion internet requise).</p>'); c.remove(); });
    return;
  }
  var g = <?= json_encode($d['graphiques'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  var score = <?= json_encode((float) $sc['valeur']) ?>;
  var couleurNiveau = <?= json_encode($couleurs[$sc['niveau']] ?? '#5d4e43') ?>;

  /* Palette sémantique unifiée pour Chart.js (identique au CSS) */
  var palette = {
    sauge: '#7a9e7e',       /* faible / succès / conforme */
    saugeClair: '#eef3ee',
    sable: '#e8dcc8',       /* moyen / neutre / information */
    sableClair: '#f9f5ef',
    ocre: '#c48a3a',        /* élevé / attention / à surveiller */
    ocreClair: '#faf3e8',
    terracotta: '#d47855',  /* critique / erreur / bloquante */
    brique: '#c45a3a',
    briqueClair: '#f5ebe8',
    brun: '#5d4e43',        /* texte principal */
    brunDoux: '#8d7b6f',    /* texte secondaire / axes */
    grisChaud: '#a09388'    /* texte faible / grilles */
  };

  var couleursGravite = {
    info: palette.sauge,        /* conforme / info -> sauge */
    moyenne: palette.sable,     /* neutre / moyen -> sable */
    elevee: palette.ocre,       /* attention / élevé -> ocre */
    bloquante: palette.brique   /* critique / bloquante -> brique */
  };

  /* Fonction pour générer une couleur RGBA à partir d'un hex */
  function hexToRgba(hex, alpha) {
    var r = parseInt(hex.slice(1, 3), 16);
    var g = parseInt(hex.slice(3, 5), 16);
    var b = parseInt(hex.slice(5, 7), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  /* 1. Jauge de score (doughnut) */
  new Chart(document.getElementById('jauge'), {
    type: 'doughnut',
    data: { datasets: [{ data: [score, 100 - score], backgroundColor: [couleurNiveau, palette.sableClair], borderWidth: 0 }] },
    options: { rotation: -90, circumference: 180, cutout: '72%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
  });

  /* 2. Évolution du score (line) */
  new Chart(document.getElementById('evolution'), {
    type: 'line',
    data: { labels: g.evolution_score.labels, datasets: [{ label: 'Score de risque', data: g.evolution_score.data, borderColor: palette.brique, backgroundColor: hexToRgba(palette.terracotta, 0.12), fill: true, tension: .25, pointRadius: 5, pointBackgroundColor: palette.brique, pointBorderColor: '#fff' }] },
    options: {
      scales: {
        y: { min: 0, max: 100, grid: { color: hexToRgba(palette.grisChaud, 0.15) }, ticks: { color: palette.brunDoux, font: { size: 11 } } },
        x: { grid: { display: false }, ticks: { color: palette.brunDoux, font: { size: 11 } } }
      },
      plugins: { legend: { display: false } }
    }
  });

  /* 3. Anomalies par règle (bar) */
  new Chart(document.getElementById('regles'), {
    type: 'bar',
    data: { labels: g.par_regle.labels, datasets: [{ label: 'Anomalies', data: g.par_regle.data, backgroundColor: palette.terracotta, borderColor: palette.brique, borderWidth: 1, borderRadius: 4 }] },
    options: {
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0, color: palette.brunDoux, font: { size: 11 } }, grid: { color: hexToRgba(palette.grisChaud, 0.15) } },
        x: { ticks: { color: palette.brunDoux, font: { size: 11 } }, grid: { display: false } }
      },
      plugins: { legend: { display: false } }
    }
  });

  /* 4. Anomalies par gravité (doughnut) */
  new Chart(document.getElementById('gravite'), {
    type: 'doughnut',
    data: { labels: g.par_gravite.labels, datasets: [{ data: g.par_gravite.data, backgroundColor: g.par_gravite.labels.map(function (l) { return couleursGravite[l]; }), borderColor: '#fff', borderWidth: 2 }] },
    options: {
      plugins: {
        legend: { position: 'bottom', labels: { color: palette.brunDoux, font: { size: 12 }, padding: 16, usePointStyle: true } },
        tooltip: { backgroundColor: palette.brun, titleColor: '#fff', bodyColor: '#fff', borderColor: palette.brique, borderWidth: 1 }
      }
    }
  });
})();
</script>
<?= $this->endSection() ?>
<?php endif; ?>