<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<p class="mini"><a href="<?= site_url('declarations') ?>">&larr; Retour aux déclarations</a></p>
<h1>Déclaration <?= esc($d['type']) ?> <?= sprintf('%02d/%d', $d['mois'], $d['annee']) ?> <?= ui_badge('decl', $d['statut']) ?></h1>
<p class="sous">Échéance : <?= esc(ui_date($d['date_echeance'])) ?><?= $d['date_depot'] ? ' - déposée le ' . esc(ui_date($d['date_depot'])) . ' (référence ' . esc($d['reference_virement']) . ')' : '' ?></p>

<?php if ($bloquantes > 0): ?>
  <div class="bandeau-bloque">Validation bloquée : <?= (int) $bloquantes ?> anomalie(s) bloquante(s) ouverte(s) sur cette période. <a href="<?= site_url('anomalies?severite=bloquante&statut=ouverte') ?>">Les traiter</a></div>
<?php elseif (in_array($d['statut'], ['brouillon', 'a_corriger', 'a_valider'], true)): ?>
  <div class="bandeau-ok">Aucune anomalie bloquante ouverte. Score de risque actuel : <?= esc(ui_nombre($score['score'])) ?> / 100 (<?= esc(ui_libelle($score['niveau'])) ?>).</div>
<?php endif; ?>

<div class="carte">
  <h2>Détail de la déclaration</h2>
  <table>
    <thead><tr><th>Ligne</th><th>Libellé</th><th class="num">Base</th><th class="num">Montant</th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $l): ?>
      <tr><td class="mono"><?= esc($l['code_ligne']) ?></td><td><?= esc($l['libelle']) ?></td><td class="num"><?= (float) $l['base'] != 0 ? esc(ui_ar($l['base'])) : '' ?></td><td class="num"><b><?= esc(ui_ar($l['montant'])) ?></b></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="mini" style="margin:10px 0 0">Les montants reprennent les écritures telles quelles, y compris celles qui portent des anomalies.</p>
</div>

<div class="carte">
  <h2>Workflow de validation</h2>
  <div class="ligne-actions">
  <?php foreach ($actions as $act):
      $noms = ['soumettre' => 'Soumettre à validation', 'renvoyer' => 'Renvoyer en correction', 'valider' => 'Valider', 'deposer' => 'Déposer', 'archiver' => 'Archiver'];
      $nom = $noms[$act['action']];
      // Les actions impossibles à cause du statut ne sont affichées que si utiles : on garde tout, grisé, pour que le circuit soit lisible.
  ?>
    <form class="action-bloc" method="post" action="<?= site_url('declarations/' . (int) $d['id'] . '/action') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= esc($act['action'], 'attr') ?>">
      <h3><?= esc($nom) ?></h3>
      <div class="mini">vers : <?= esc(ui_libelle($act['vers'])) ?></div>
      <?php if ($act['autorisee']): ?>
        <?php if ($act['action'] === 'deposer'): ?>
          <label>Référence du virement (obligatoire)</label><input type="text" name="reference_virement" placeholder="VIR-2026-10-0001">
        <?php else: ?>
          <label>Commentaire<?= $act['action'] === 'renvoyer' ? ' (obligatoire)' : ' (facultatif)' ?></label><textarea name="commentaire"></textarea>
        <?php endif; ?>
        <p style="margin:10px 0 0"><button class="btn" type="submit"><?= esc($nom) ?></button></p>
      <?php else: ?>
        <p style="margin:10px 0 0"><button class="btn" type="button" disabled><?= esc($nom) ?></button></p>
        <div class="raison"><?= esc($act['raison']) ?></div>
      <?php endif; ?>
    </form>
  <?php endforeach; ?>
  </div>
</div>

<div class="carte">
  <h2>Historique des validations</h2>
  <?php if ($validations === []): ?><p class="mini">Aucune validation pour l'instant.</p><?php else: ?>
  <table>
    <thead><tr><th>Date</th><th>Utilisateur</th><th>Étape</th><th>Décision</th><th>Commentaire</th></tr></thead>
    <tbody>
    <?php foreach ($validations as $v): ?>
      <tr><td><?= esc(ui_date($v['created_at'], true)) ?></td><td><?= esc($v['nom']) ?></td><td><?= esc(ui_libelle($v['etape'])) ?></td><td><?= esc(ui_libelle($v['decision'])) ?></td><td><?= esc($v['commentaire'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
