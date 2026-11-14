<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<h1>Règles de contrôle</h1>
<p class="sous">Poids, gravité, activation et seuils sont stockés en base : on les modifie ici sans toucher au code. Chaque modification exige un motif, est tracée dans le journal d'audit et recalcule le score de la période affichée (<?= isset($ctx['periode']) ? sprintf('%02d/%d', $ctx['periode']['mois'], $ctx['periode']['annee']) : '-' ?>).</p>

<?php $res = session()->getFlashdata('resultat_regle'); if (is_array($res)): ?>
<div class="carte" style="border-color:<?= $res['applique'] ? '#a5d6a7' : '#90caf9' ?>">
  <h2><?= $res['applique'] ? 'Modification appliquée' : 'Aperçu : rien n\'est enregistré' ?> - règle <?= esc($res['regle']) ?></h2>
  <ul style="margin:0 0 10px;padding-left:20px">
  <?php foreach ($res['modifications'] as $champ => $m): ?>
    <?php if ($champ === 'parametres'): foreach ($m as $k => $v): ?>
      <li><?= esc($k) ?> : <?= esc(json_encode($v['avant'])) ?> &rarr; <b><?= esc(json_encode($v['apres'])) ?></b></li>
    <?php endforeach; else: ?>
      <li><?= esc($champ) ?> : <?= esc(json_encode($m['avant'], JSON_UNESCAPED_UNICODE)) ?> &rarr; <b><?= esc(json_encode($m['apres'], JSON_UNESCAPED_UNICODE)) ?></b></li>
    <?php endif; endforeach; ?>
  </ul>
  <p style="margin:0;font-size:16px">Score : <?= esc(ui_nombre($res['score_avant'])) ?> (<?= esc(ui_libelle($res['niveau_avant'])) ?>) &rarr; <b><?= esc(ui_nombre($res['score_apres'])) ?> (<?= esc(ui_libelle($res['niveau_apres'])) ?>)</b>, variation <?= $res['variation'] > 0 ? '+' : '' ?><?= esc(ui_nombre($res['variation'])) ?></p>
  <p class="mini" style="margin:6px 0 0">Anomalies : <?= (int) $res['anomalies_avant'] ?> &rarr; <?= (int) $res['anomalies_apres'] ?> ; bloquantes ouvertes : <?= (int) $res['bloquantes_avant'] ?> &rarr; <?= (int) $res['bloquantes_apres'] ?> (<?= $res['validation_bloquee_apres'] ? 'validation bloquée' : 'validation possible' ?>)</p>
</div>
<?php endif; ?>

<?php foreach ($regles as $r): ?>
<form class="carte" id="regle-<?= (int) $r['id'] ?>" method="post" action="<?= site_url('regles/' . (int) $r['id']) ?>">
  <?= csrf_field() ?>
  <h2><?= esc($r['code']) ?> - <?= esc($r['intitule']) ?> <?= $r['active'] ? '' : '<span class="badge gravite-info">désactivée</span>' ?> <?= ui_badge('gravite', $r['gravite']) ?></h2>
  <p class="mini" style="margin-top:-6px">Base légale : <?= esc($r['source_legale'] ?? '-') ?></p>
  <div class="grille g4">
    <div><label>Poids (0 à 10)</label><input type="number" name="poids" min="0" max="10" step="0.5" value="<?= esc((string) $r['poids'], 'attr') ?>"></div>
    <div><label>Gravité</label><select name="gravite">
      <?php foreach (['info', 'moyenne', 'elevee', 'bloquante'] as $g): ?><option value="<?= $g ?>"<?= $r['gravite'] === $g ? ' selected' : '' ?>><?= esc(ui_libelle($g)) ?></option><?php endforeach; ?></select></div>
    <div><label>Règle active</label><input type="hidden" name="active" value="0"><label style="margin:0;color:inherit"><input type="checkbox" name="active" value="1"<?= $r['active'] ? ' checked' : '' ?>> oui</label></div>
    <?php foreach ($r['parametres'] as $k => $v): ?>
      <div><label><?= esc($k) ?></label>
      <?php if (is_bool($v)): ?>
        <input type="hidden" name="parametres[<?= esc($k, 'attr') ?>]" value="0"><label style="margin:0;color:inherit"><input type="checkbox" name="parametres[<?= esc($k, 'attr') ?>]" value="1"<?= $v ? ' checked' : '' ?>> oui</label>
      <?php elseif (is_int($v) || is_float($v)): ?>
        <input type="number" min="0" step="any" name="parametres[<?= esc($k, 'attr') ?>]" value="<?= esc((string) $v, 'attr') ?>">
      <?php else: ?>
        <input type="text" value="<?= esc((string) $v, 'attr') ?>" disabled title="Code technique : non modifiable ici">
      <?php endif; ?></div>
    <?php endforeach; ?>
  </div>
  <label>Motif de la modification (obligatoire pour appliquer)</label>
  <input type="text" name="motif" placeholder="Ex. : risque accru sur la TVA ce trimestre">
  <p style="margin:14px 0 0;display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn sec" type="submit" name="mode" value="apercu">Aperçu (sans enregistrer)</button>
    <button class="btn" type="submit" name="mode" value="appliquer">Appliquer</button>
  </p>
  <?php if ($r['gravite'] === 'bloquante'): ?><p class="mini" style="margin:8px 0 0">Règle bloquante : la désactiver ou la déclasser est réservé à l'administrateur.</p><?php endif; ?>
</form>
<?php endforeach; ?>
<?= $this->endSection() ?>
