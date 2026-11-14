<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<h1>Journal d'audit</h1>
<p class="sous">Chaque ligne contient l'empreinte (SHA-256) de la précédente : modifier ou supprimer une ligne rompt la chaîne.</p>

<?php if ($chaine['ok']): ?>
  <div class="bandeau-ok">Chaîne de hash intacte : <?= (int) $chaine['nb'] ?> ligne(s) vérifiée(s).</div>
<?php else: ?>
  <div class="bandeau-bloque">Journal ALTÉRÉ à la ligne <?= (int) $chaine['premiere_erreur'] ?> : <?= esc($chaine['raison']) ?>.</div>
<?php endif; ?>

<div class="carte">
  <table>
    <thead><tr><th>N°</th><th>Date</th><th>Utilisateur</th><th>Action</th><th>Cible</th><th>Détail</th><th>Empreinte</th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $l):
        $apres = json_decode((string) $l['donnees_apres'], true) ?: [];
        $resume = [];
        foreach (['statut', 'commentaire', 'motif', 'regle', 'score', 'niveau', 'reference_virement'] as $c) {
            if (isset($apres[$c]) && $apres[$c] !== '') { $resume[] = $c . ' : ' . $apres[$c]; }
        } ?>
      <tr>
        <td><?= (int) $l['id'] ?></td>
        <td class="mini" style="white-space:nowrap"><?= esc(ui_date($l['created_at'], true)) ?></td>
        <td><?= esc($l['nom'] ?? 'système') ?></td>
        <td><b><?= esc($l['action']) ?></b></td>
        <td class="mini"><?= esc($l['table_cible']) ?><?= $l['enregistrement_id'] ? ' #' . (int) $l['enregistrement_id'] : '' ?></td>
        <td class="mini"><?= esc(mb_strimwidth(implode(' ; ', $resume), 0, 120, '...')) ?></td>
        <td class="mono mini"><?= esc(substr($l['hash_courant'], 0, 12)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="mini" style="margin:10px 0 0">Les 150 dernières lignes sont affichées.</p>
</div>
<?= $this->endSection() ?>
