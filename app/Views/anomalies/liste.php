<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<h1>Anomalies</h1>
<p class="sous"><?= esc($ctx['societe']['raison_sociale'] ?? '') ?> - période <?= isset($ctx['periode']) ? sprintf('%02d/%d', $ctx['periode']['mois'], $ctx['periode']['annee']) : '-' ?></p>

<?php if ($score !== null): ?>
<div class="carte" style="display:flex;flex-wrap:wrap;gap:14px 30px;align-items:center;justify-content:space-between">
  <div>Score de risque : <b style="font-size:22px"><?= esc(ui_nombre($score['score'])) ?> / 100</b> <?= ui_badge('niveau', $score['niveau']) ?>
    <?php $b = $score['detail']['anomalies_bloquantes_ouvertes'] ?? 0; ?>
    <span class="mini" style="margin-left:10px"><?= $b > 0 ? $b . ' anomalie(s) bloquante(s) ouverte(s) : validation bloquée' : 'aucune anomalie bloquante ouverte' ?></span></div>
  <form method="post" action="<?= site_url('anomalies/recalculer') ?>"><?= csrf_field() ?>
    <button class="btn sec" type="submit" title="Rejoue les règles sur la période après correction des écritures">Recalculer les anomalies</button></form>
</div>
<?php endif; ?>

<div class="carte">
  <form method="get" action="<?= site_url('anomalies') ?>" class="filtres">
    <div><label for="f-regle">Règle</label><select id="f-regle" name="regle"><option value="">Toutes</option>
      <?php foreach ($regles ?? [] as $r): ?><option value="<?= esc($r['code'], 'attr') ?>"<?= ($filtres['regle'] ?? '') === $r['code'] ? ' selected' : '' ?>><?= esc($r['code']) ?></option><?php endforeach; ?></select></div>
    <div><label for="f-sev">Gravité</label><select id="f-sev" name="severite"><option value="">Toutes</option>
      <?php foreach (['bloquante', 'elevee', 'moyenne', 'info'] as $g): ?><option value="<?= $g ?>"<?= ($filtres['severite'] ?? '') === $g ? ' selected' : '' ?>><?= esc(ui_libelle($g)) ?></option><?php endforeach; ?></select></div>
    <div><label for="f-st">Statut</label><select id="f-st" name="statut"><option value="">Tous</option>
      <?php foreach (['ouverte', 'justifiee', 'corrigee', 'ignoree'] as $s): ?><option value="<?= $s ?>"<?= ($filtres['statut'] ?? '') === $s ? ' selected' : '' ?>><?= esc(ui_libelle($s)) ?></option><?php endforeach; ?></select></div>
    <div><button class="btn" type="submit">Filtrer</button> <a class="btn sec" href="<?= site_url('anomalies') ?>">Réinitialiser</a></div>
  </form>

  <?php if ($anomalies === []): ?>
    <p class="mini">Aucune anomalie pour ces critères.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Règle</th><th>Gravité</th><th>Statut</th><th>Anomalie</th><th class="num">Montant à risque</th></tr></thead>
    <tbody>
    <?php foreach ($anomalies as $a): ?>
      <tr>
        <td><a href="<?= site_url('anomalies/' . $a['id']) ?>"><b><?= esc($a['regle_code']) ?></b></a></td>
        <td><?= ui_badge('gravite', $a['severite']) ?></td>
        <td><?= ui_badge('statut', $a['statut']) ?></td>
        <td><a href="<?= site_url('anomalies/' . $a['id']) ?>" style="text-decoration:none;color:inherit"><?= esc($a['message']) ?></a></td>
        <td class="num"><?= esc(ui_ar($a['montant_concerne'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="mini" style="margin:10px 0 0"><?= count($anomalies) ?> anomalie(s). Les règles désactivées n'apparaissent pas.</p>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
