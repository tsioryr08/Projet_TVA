<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<?php $a = $anomalie; ?>
<p class="mini"><a href="<?= site_url('anomalies') ?>">&larr; Retour aux anomalies</a></p>
<h1>Anomalie <?= esc($a['regle_code']) ?> <?= ui_badge('gravite', $a['severite']) ?> <?= ui_badge('statut', $a['statut']) ?></h1>
<p class="sous"><?= esc($a['regle_intitule']) ?></p>

<div class="carte">
  <h2>Constat</h2>
  <p style="margin-top:0;font-size:16px"><?= esc($a['message']) ?></p>
  <table>
    <tbody>
      <tr><th style="width:210px">Montant à risque</th><td><b><?= esc(ui_ar($a['montant_concerne'])) ?></b></td></tr>
      <tr><th>Action corrective</th><td><?= esc($a['action_corrective'] ?? '-') ?></td></tr>
      <tr><th>Règle appliquée</th><td><?= esc($a['regle_description']) ?></td></tr>
      <tr><th>Base légale</th><td><?= esc($a['source_legale'] ?? '-') ?></td></tr>
      <tr><th>Détectée le</th><td><?= esc(ui_date($a['detectee_le'], true)) ?></td></tr>
      <?php if (! empty($a['justification'])): ?><tr><th>Dernier commentaire</th><td><?= esc($a['justification']) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="carte">
  <h2>Dossier de preuve</h2>
  <?php if ($preuves === []): ?><p class="mini">Aucune ligne source enregistrée.</p><?php else: ?>
  <table>
    <thead><tr><th>Pièce ou ligne</th><th>Source</th><th class="num">Montant</th></tr></thead>
    <tbody>
    <?php foreach ($preuves as $p): ?>
      <tr><td><?= esc($p['libelle']) ?></td><td class="mini"><?= esc($p['table_source']) ?><?= $p['enregistrement_id'] ? ' n° ' . (int) $p['enregistrement_id'] : '' ?></td><td class="num"><?= esc(ui_ar($p['montant'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="carte">
  <h2>Traiter l'anomalie</h2>
  <div class="ligne-actions">
  <?php foreach ($actions as $act):
      $nom = ['justifier' => 'Justifier', 'corriger' => 'Marquer corrigée', 'ignorer' => 'Ignorer', 'rouvrir' => 'Rouvrir'][$act['action']];
      $aide = ['justifier' => 'Expliquez pourquoi cet écart est acceptable (10 caractères minimum).', 'corriger' => 'À utiliser après avoir corrigé l\'écriture ou le bulletin : le moteur vérifie que l\'anomalie a disparu.', 'ignorer' => 'Retire l\'anomalie du score. Commentaire obligatoire.', 'rouvrir' => 'Remet l\'anomalie à traiter. Commentaire obligatoire.'][$act['action']]; ?>
    <form class="action-bloc" method="post" action="<?= site_url('anomalies/' . (int) $a['id'] . '/traiter') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= esc($act['action'], 'attr') ?>">
      <h3><?= esc($nom) ?></h3>
      <div class="mini"><?= esc($aide) ?></div>
      <?php if ($act['autorisee']): ?>
        <label for="c-<?= esc($act['action'], 'attr') ?>">Commentaire</label>
        <textarea id="c-<?= esc($act['action'], 'attr') ?>" name="commentaire"></textarea>
        <p style="margin:10px 0 0"><button class="btn<?= $act['action'] === 'ignorer' ? ' danger' : '' ?>" type="submit"><?= esc($nom) ?></button></p>
      <?php else: ?>
        <p style="margin:10px 0 0"><button class="btn" type="button" disabled><?= esc($nom) ?></button></p>
        <div class="raison"><?= esc($act['raison']) ?></div>
      <?php endif; ?>
    </form>
  <?php endforeach; ?>
  </div>
</div>

<div class="carte">
  <h2>Historique des traitements</h2>
  <?php if ($historique === []): ?><p class="mini">Aucun traitement pour l'instant.</p><?php else: ?>
  <table>
    <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Statut</th><th>Commentaire</th></tr></thead>
    <tbody>
    <?php foreach ($historique as $h): ?>
      <tr><td><?= esc(ui_date($h['date'], true)) ?></td><td><?= esc($h['utilisateur'] ?? '-') ?></td><td><?= esc($h['action']) ?></td><td><?= esc(ui_libelle((string) $h['de'])) ?> &rarr; <?= esc(ui_libelle((string) $h['vers'])) ?></td><td><?= esc($h['commentaire'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
