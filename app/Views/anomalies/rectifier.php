<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<?php $a = $anomalie; $ar = fn ($v) => number_format((float) $v, 0, ',', ' '); ?>
<p class="mini"><a href="<?= site_url('anomalies/' . (int) $a['id']) ?>">&larr; Retour à l'anomalie</a></p>
<h1>Rectifier les données <?= ui_badge('gravite', $a['severite']) ?></h1>
<p class="sous">Anomalie <?= esc($a['regle_code']) ?> : <?= esc($a['message']) ?></p>

<?php if (! $modifiable): ?>
<div class="flash info"><?= esc((string) $raison) ?></div>
<?php endif; ?>

<?php if ($type === 'bulletin'): $b = $bulletin; ?>
<div class="carte">
  <h2>Bulletin de paie : <?= esc($b['prenom'] . ' ' . $b['nom']) ?> (<?= esc($b['matricule']) ?>)</h2>
  <table><tbody>
    <tr><th style="width:210px">Salaire brut</th><td><?= esc($ar($b['salaire_brut'])) ?> Ar</td></tr>
    <tr><th>Base imposable</th><td><?= esc($ar($b['base_imposable'])) ?> Ar</td></tr>
    <tr><th>IRSA retenu actuellement</th><td><b><?= esc($ar($b['irsa_retenu'])) ?> Ar</b></td></tr>
    <tr><th>Net à payer actuel</th><td><?= esc($ar($b['net_a_payer'])) ?> Ar</td></tr>
  </tbody></table>
  <form method="post" action="<?= site_url('anomalies/' . (int) $a['id'] . '/rectifier') ?>" style="margin-top:14px">
    <?= csrf_field() ?>
    <div class="grille g4">
      <div><label for="r-irsa">IRSA retenu corrigé (Ar)</label><input id="r-irsa" type="text" name="irsa_retenu" inputmode="decimal" required value="<?= esc((string) (old('irsa_retenu') ?? (int) $b['irsa_retenu']), 'attr') ?>"></div>
    </div>
    <p class="mini">Le net à payer est ajusté automatiquement de la même différence. L'IRSA attendu est indiqué dans le constat de l'anomalie.</p>
    <p style="margin:14px 0 0"><button class="btn" type="submit"<?= $modifiable ? '' : ' disabled' ?>>Enregistrer la rectification</button></p>
  </form>
</div>

<?php else: $e = $ecriture; ?>
<div class="carte">
  <h2>Écriture <?= esc($e['numero_piece']) ?> (journal <?= esc($e['journal']) ?>)</h2>
  <form method="post" action="<?= site_url('anomalies/' . (int) $a['id'] . '/rectifier') ?>">
    <?= csrf_field() ?>
    <div class="grille g4">
      <div><label for="r-date">Date de l'écriture</label><input id="r-date" type="date" name="date_ecriture" required value="<?= esc((string) (old('date_ecriture') ?? $e['date_ecriture']), 'attr') ?>"></div>
      <div><label for="r-piece">Numéro de pièce</label><input id="r-piece" type="text" name="numero_piece" maxlength="50" required value="<?= esc((string) (old('numero_piece') ?? $e['numero_piece']), 'attr') ?>"></div>
      <div><label for="r-lib">Libellé</label><input id="r-lib" type="text" name="libelle" maxlength="255" value="<?= esc((string) (old('libelle') ?? $e['libelle']), 'attr') ?>"></div>
      <div><label for="r-doc">Document justificatif</label><select id="r-doc" name="document_id"><option value="">Aucun</option>
        <?php $docCourant = (string) (old('document_id') ?? $e['document_id']); foreach ($documents as $d): ?>
        <option value="<?= (int) $d['id'] ?>"<?= $docCourant === (string) $d['id'] ? ' selected' : '' ?>>n° <?= (int) $d['id'] ?> - <?= esc($d['nom_fichier']) ?></option>
        <?php endforeach; ?></select></div>
    </div>

    <h3 style="margin:18px 0 6px">Lignes de l'écriture</h3>
    <table>
      <thead><tr><th>Compte</th><th>Libellé</th><th class="num">Débit (Ar)</th><th class="num">Crédit (Ar)</th></tr></thead>
      <tbody>
      <?php foreach ($lignes as $l): $id = (int) $l['id']; ?>
        <tr>
          <td class="mono"><?= esc($l['compte']) ?></td>
          <td><?= esc($l['compte_libelle'] ?? '') ?></td>
          <td class="num"><input type="text" name="debit[<?= $id ?>]" inputmode="decimal" aria-label="Débit du compte <?= esc($l['compte'], 'attr') ?>" style="text-align:right" value="<?= esc((string) (old('debit')[$id] ?? ((float) $l['debit'] > 0 ? (int) $l['debit'] : '')), 'attr') ?>"></td>
          <td class="num"><input type="text" name="credit[<?= $id ?>]" inputmode="decimal" aria-label="Crédit du compte <?= esc($l['compte'], 'attr') ?>" style="text-align:right" value="<?= esc((string) (old('credit')[$id] ?? ((float) $l['credit'] > 0 ? (int) $l['credit'] : '')), 'attr') ?>"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="mini">Le total des débits doit être égal au total des crédits.</p>

    <?php if ($tiers !== null): ?>
    <h3 style="margin:18px 0 6px">Tiers : <?= esc($tiers['raison_sociale']) ?></h3>
    <div class="grille g4">
      <div><label for="r-nif">NIF du tiers</label><input id="r-nif" type="text" name="tiers_nif" value="<?= esc((string) (old('tiers_nif') ?? $tiers['nif']), 'attr') ?>">
        <span class="mini"><?= (int) $tiers['nif_valide'] === 1 ? 'NIF actuellement valide.' : 'NIF actuellement absent ou non valide.' ?></span></div>
    </div>
    <?php endif; ?>

    <p style="margin:18px 0 0"><button class="btn" type="submit"<?= $modifiable ? '' : ' disabled' ?>>Enregistrer la rectification</button></p>
  </form>
</div>

<?php if (! empty($doublon) && $modifiable): ?>
<div class="carte">
  <h2>Écriture en double</h2>
  <p class="mini" style="margin-top:0">Cette facture a été comptabilisée deux fois. Supprimez l'écriture en trop (la première comptabilisation est conservée). Le motif est enregistré dans le journal d'audit.</p>
  <form method="post" action="<?= site_url('anomalies/' . (int) $a['id'] . '/rectifier/supprimer') ?>">
    <?= csrf_field() ?>
    <label for="r-motif">Motif de la suppression</label>
    <textarea id="r-motif" name="motif" required></textarea>
    <p style="margin:10px 0 0"><button class="btn danger" type="submit">Supprimer l'écriture en double</button></p>
  </form>
</div>
<?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>