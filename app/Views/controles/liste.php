<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<h1>Historique des contrôles fiscaux</h1>
<p class="sous"><?= esc($ctx['societe']['raison_sociale'] ?? '') ?> - un redressement passé sur une règle qui se déclenche aujourd'hui ajoute +10 au score (facteur historique).</p>

<?php if ($peutSaisir): ?>
<div class="carte">
  <h2>Enregistrer un contrôle</h2>
  <form method="post" action="<?= site_url('controles') ?>">
    <?= csrf_field() ?>
    <div class="grille g4">
      <div><label for="c-date">Date du contrôle</label><input id="c-date" type="date" name="date_controle" required value="<?= esc((string) old('date_controle'), 'attr') ?>"></div>
      <div><label for="c-impot">Impôt</label><select id="c-impot" name="type_impot">
        <?php foreach (['TVA', 'IRSA', 'RETENUE'] as $t): ?><option value="<?= $t ?>"<?= old('type_impot') === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
      <div><label for="c-regle">Règle concernée</label><select id="c-regle" name="regle_code"><option value="">Aucune précise</option>
        <?php foreach ($regles as $r): ?><option value="<?= esc($r['code'], 'attr') ?>"<?= old('regle_code') === $r['code'] ? ' selected' : '' ?>><?= esc($r['code'] . ' - ' . $r['intitule']) ?></option><?php endforeach; ?></select></div>
      <div><label for="c-montant">Redressement (Ar)</label><input id="c-montant" type="text" name="redressement_montant" inputmode="decimal" required value="<?= esc((string) old('redressement_montant', '0'), 'attr') ?>"></div>
    </div>
    <label for="c-motif">Motif</label>
    <textarea id="c-motif" name="motif" required maxlength="1000"><?= esc((string) old('motif')) ?></textarea>
    <p style="margin:14px 0 0"><button class="btn" type="submit">Enregistrer le contrôle</button></p>
  </form>
</div>
<?php else: ?>
<div class="flash info">La saisie de l'historique est réservée au responsable fiscal. Vous pouvez consulter la liste.</div>
<?php endif; ?>

<div class="carte">
  <h2>Contrôles enregistrés</h2>
  <?php if ($controles === []): ?><p class="mini">Aucun contrôle enregistré pour cette société.</p><?php else: ?>
  <table>
    <thead><tr><th>Date</th><th>Impôt</th><th>Règle</th><th>Motif</th><th class="num">Redressement</th></tr></thead>
    <tbody>
    <?php foreach ($controles as $c): ?>
      <tr>
        <td><?= esc(ui_date($c['date_controle'])) ?></td>
        <td><?= esc($c['type_impot']) ?></td>
        <td><?= esc($c['regle_code'] ?? '-') ?></td>
        <td><?= esc($c['motif'] ?? '') ?></td>
        <td class="num"><?= esc(ui_ar($c['redressement_montant'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
