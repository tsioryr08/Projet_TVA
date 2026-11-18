<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<h1>Bordereaux et pièces</h1>
<p class="sous"><?= esc($ctx['societe']['raison_sociale'] ?? '') ?> - période <?= isset($ctx['periode']) ? sprintf('%02d/%d', $ctx['periode']['mois'], $ctx['periode']['annee']) : '-' ?></p>

<?php if ($peutSaisir && ! empty($ctx['periode'])): ?>
<div class="carte">
  <h2>Saisir un bordereau</h2>
  <p class="mini">Choisir le PDF : le montant, la date et le NIF sont lus automatiquement et pré-remplis. Vérifiez-les (et corrigez si besoin) avant d'enregistrer. Si le PDF n'est pas lisible, saisissez les champs à la main.</p>
  <p id="b-lecture" class="mini" role="status" aria-live="polite"></p>
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
  <script>
  (function () {
    var form = document.querySelector('form[action$="/bordereaux"]'), pdf = document.getElementById('b-pdf'), msg = document.getElementById('b-lecture');
    if (!form || !pdf || !window.fetch || !window.FormData) { return; }
    pdf.addEventListener('change', function () {
      if (!pdf.files.length) { return; }
      var tokenInput = form.querySelector('input[type=hidden][name="<?= csrf_token() ?>"]');
      var fd = new FormData();
      fd.append('fichier', pdf.files[0]);
      if (tokenInput) { fd.append(tokenInput.name, tokenInput.value); }
      msg.textContent = 'Lecture du PDF...';
      fetch('<?= site_url('bordereaux/lire') ?>', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json().catch(function () { return { message: 'Lecture impossible : saisissez les champs à la main.' }; }); })
        .then(function (j) {
          if (j.token && tokenInput) { tokenInput.value = j.token; }
          [['b-montant', j.montant], ['b-date', j.date], ['b-nif', j.nif]].forEach(function (c) {
            var champ = document.getElementById(c[0]);
            if (champ && c[1]) { champ.value = c[1]; }
          });
          msg.textContent = j.message || '';
        })
        .catch(function () { msg.textContent = 'Lecture impossible : saisissez les champs à la main.'; });
    });
  })();
  </script>
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