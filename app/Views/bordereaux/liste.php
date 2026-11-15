<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<h1>Bordereaux et pièces</h1>
<p class="sous"><?= esc($ctx['societe']['raison_sociale'] ?? '') ?> - période <?= isset($ctx['periode']) ? sprintf('%02d/%d', $ctx['periode']['mois'], $ctx['periode']['annee']) : '-' ?></p>

<?php if ($peutSaisir && ! empty($ctx['periode'])): ?>
<div class="carte">
  <h2>Saisir un bordereau</h2>
  <p class="mini">Joindre le PDF, puis recopier les trois champs lus sur le document. L'extraction automatique du texte des PDF n'est pas prévue dans cette version (choix assumé, prévue en évolution).</p>
  <form method="post" action="<?= site_url('bordereaux') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="grille g4">
      <div><label for="b-type">Type de document</label><select id="b-type" name="type_document">
        <?php foreach (['bordereau', 'facture', 'bulletin', 'autre'] as $t): ?><option value="<?= $t ?>"<?= old('type_document', 'bordereau') === $t ? ' selected' : '' ?>><?= esc(ucfirst($t)) ?></option><?php endforeach; ?></select></div>
      <div><label for="b-tiers">Tiers (facultatif)</label><select id="b-tiers" name="tiers_id"><option value="">-</option>
        <?php foreach ($tiers as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (string) old('tiers_id') === (string) $t['id'] ? ' selected' : '' ?>><?= esc($t['raison_sociale']) ?></option><?php endforeach; ?></select></div>
      <div><label for="b-montant">Montant TTC (Ar)</label><input id="b-montant" type="text" name="montant_ttc" inputmode="decimal" required value="<?= esc((string) old('montant_ttc'), 'attr') ?>"></div>
      <div><label for="b-date">Date du document</label><input id="b-date" type="date" name="date_document" required value="<?= esc((string) old('date_document'), 'attr') ?>"></div>
      <div><label for="b-nif">NIF de l'émetteur</label><input id="b-nif" type="text" name="nif_emetteur" required value="<?= esc((string) old('nif_emetteur'), 'attr') ?>"></div>
      <div><label for="b-pdf">PDF joint (5 Mo max)</label><input id="b-pdf" type="file" name="fichier" accept="application/pdf,.pdf" required></div>
    </div>
    <p style="margin:14px 0 0"><button class="btn" type="submit">Enregistrer le bordereau</button></p>
  </form>
</div>
<?php elseif (! $peutSaisir): ?>
<div class="flash info">La saisie des bordereaux est réservée au comptable. Vous pouvez consulter la liste.</div>
<?php endif; ?>

<div class="carte">
  <h2>Documents enregistrés</h2>
  <?php if ($documents === []): ?><p class="mini">Aucun document pour cette période.</p><?php else: ?>
  <table>
    <thead><tr><th>N°</th><th>Type</th><th>Date</th><th>Tiers</th><th>NIF émetteur</th><th class="num">Montant TTC</th><th>Fichier</th><th>Saisi le</th></tr></thead>
    <tbody>
    <?php foreach ($documents as $d): ?>
      <tr>
        <td><?= (int) $d['id'] ?></td>
        <td><?= esc(ucfirst($d['type_document'])) ?></td>
        <td><?= esc(ui_date($d['champs']['date_document'] ?? null)) ?></td>
        <td><?= esc($d['tiers'] ?? '-') ?></td>
        <td class="mono"><?= esc($d['champs']['nif_emetteur'] ?? '-') ?></td>
        <td class="num"><?= isset($d['champs']['montant_ttc']) ? esc(ui_ar($d['champs']['montant_ttc'])) : '-' ?></td>
        <td><a href="<?= site_url('bordereaux/' . (int) $d['id'] . '/fichier') ?>" target="_blank" rel="noopener"><?= esc($d['nom_fichier']) ?></a></td>
        <td class="mini"><?= esc(ui_date($d['created_at'], true)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>
