<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<h1>Déclarations</h1>
<p class="sous"><?= esc($ctx['societe']['raison_sociale'] ?? '') ?></p>

<?php if (! empty($ctx['periode']) && in_array($user['role'], ['comptable', 'admin'], true)): ?>
<div class="carte">
  <h2>Générer une déclaration pour la période <?= sprintf('%02d/%d', $ctx['periode']['mois'], $ctx['periode']['annee']) ?></h2>
  <p class="mini">La déclaration est calculée à partir des écritures et des bulletins de la période, puis suit le workflow de validation.</p>
  <form method="post" action="<?= site_url('declarations/generer') ?>" style="display:flex;gap:10px;flex-wrap:wrap"><?= csrf_field() ?>
    <button class="btn" name="type" value="TVA" type="submit">Générer la TVA</button>
    <button class="btn" name="type" value="IRSA" type="submit">Générer l'IRSA</button>
    <button class="btn" name="type" value="RETENUE" type="submit">Générer les retenues</button>
  </form>
</div>
<?php endif; ?>

<div class="carte">
  <h2>Déclarations de la société</h2>
  <?php if ($declarations === []): ?><p class="mini">Aucune déclaration pour le moment.</p><?php else: ?>
  <table>
    <thead><tr><th>Type</th><th>Période</th><th>Statut</th><th class="num">Montant</th><th>Échéance</th><th>Dépôt</th></tr></thead>
    <tbody>
    <?php foreach ($declarations as $d): ?>
      <tr>
        <td><a href="<?= site_url('declarations/' . $d['id']) ?>"><b><?= esc($d['type']) ?></b></a></td>
        <td><?= sprintf('%02d/%d', $d['mois'], $d['annee']) ?></td>
        <td><?= ui_badge('decl', $d['statut']) ?></td>
        <td class="num"><?= esc(ui_ar($d['montant_total'])) ?></td>
        <td><?= esc(ui_date($d['date_echeance'])) ?></td>
        <td><?= $d['date_depot'] ? esc(ui_date($d['date_depot'])) . ' <span class="mini">(' . esc($d['reference_virement']) . ')</span>' : '-' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
