<?= $this->extend('layout') ?>
<?= $this->section('contenu') ?>
<?php
/** @var list<array<string,mixed>> $lignes */
$estComptable = ($user['role'] ?? '') === 'comptable';
?>
<h1>Simulateur : et si je corrige ?</h1>
<p class="sous"><?= esc($ctx['societe']['raison_sociale'] ?? '') ?> - période <?= isset($ctx['periode']) ? sprintf('%02d/%d', $ctx['periode']['mois'], $ctx['periode']['annee']) : '-' ?></p>

<?php if ($score === null): ?>
<div class="carte"><p class="mini">Aucune période sélectionnée : il n'y a rien à simuler.</p></div>
<?php else: ?>

<div class="carte" style="display:flex;flex-wrap:wrap;gap:14px 30px;align-items:center;justify-content:space-between">
  <div>Score actuel : <b style="font-size:22px"><?= esc(ui_nombre($score['score'])) ?> / 100</b> <?= ui_badge('niveau', $score['niveau']) ?>
    <?php $b = (int) ($score['detail']['anomalies_bloquantes_ouvertes'] ?? 0); ?>
    <span class="mini" style="margin-left:10px"><?= $b > 0 ? esc($b) . ' anomalie(s) bloquante(s) ouverte(s) : validation bloquée' : 'aucune anomalie bloquante ouverte' ?></span></div>
  <div class="mini"><b>Simulation : rien n'est enregistré.</b></div>
</div>

<?php if (! empty($erreurSimulation)): ?>
<div class="flash erreur"><?= esc($erreurSimulation) ?></div>
<?php endif; ?>

<?php if (! empty($resultat)): ?>
<div class="carte" style="border-left:5px solid var(--<?= $resultat['soumettable_apres'] ? 'vert' : 'rouge' ?>)">
  <h2>Résultat de la simulation</h2>
  <p style="margin:0 0 8px">Score projeté : <b style="font-size:22px"><?= esc(ui_nombre($resultat['score_projete'])) ?> / 100</b>
    (<?= ui_badge('niveau', $resultat['niveau_projete']) ?>), variation
    <b><?= esc(($resultat['delta'] > 0 ? '+' : ($resultat['delta'] < 0 ? '-' : '')) . ui_nombre(abs($resultat['delta']))) ?></b>
    <span class="mini">(actuel : <?= esc(ui_nombre($resultat['score_actuel'])) ?> / 100, <?= esc(ui_libelle($resultat['niveau_actuel'])) ?>)</span></p>
  <p style="margin:0 0 8px">Anomalies bloquantes ouvertes : <b><?= esc($resultat['bloquantes_avant']) ?></b> avant, <b><?= esc($resultat['bloquantes_apres']) ?></b> après.</p>
  <p style="margin:0 0 8px"><b><?= $resultat['soumettable_apres']
      ? 'La déclaration pourrait être soumise'
      : esc('Resterait bloquée : ' . $resultat['bloquantes_apres'] . ' anomalie(s) bloquante(s)') ?></b></p>
  <p class="mini" style="margin:0 0 12px"><?= esc($resultat['nb_corrigees']) ?> correction(s) et <?= esc($resultat['nb_justifiees']) ?> justification(s) simulée(s). <b>Simulation : rien n'est enregistré</b> (ni anomalie, ni score, ni journal d'audit).</p>

  <table>
    <thead><tr><th>Règle</th><th class="num">Anomalies</th><th class="num">Points avant</th><th class="num">Points après</th><th class="num">Contribution avant</th><th class="num">Contribution après</th></tr></thead>
    <tbody>
    <?php foreach ($resultat['detail_regles'] as $r): ?>
      <tr>
        <td><b><?= esc($r['code']) ?></b> <span class="mini"><?= esc($r['intitule']) ?></span></td>
        <td class="num"><?= esc($r['nb_anomalies']) ?></td>
        <td class="num"><?= esc(ui_nombre($r['points_avant'], 2)) ?></td>
        <td class="num"><?= esc(ui_nombre($r['points_apres'], 2)) ?></td>
        <td class="num"><?= esc(ui_nombre($r['contribution_avant'], 2)) ?></td>
        <td class="num"><?= esc(ui_nombre($r['contribution_apres'], 2)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="carte">
  <?php if ($lignes === []): ?>
    <p class="mini">Aucune anomalie ouverte sur cette période : rien à simuler.</p>
  <?php else: ?>
  <form method="post" action="<?= site_url('simulateur') ?>"><?= csrf_field() ?>
    <table>
      <thead><tr><th>Règle</th><th>Gravité</th><th>Anomalie</th><th class="num">Gain si corrigée</th><th class="num">Gain si justifiée</th><th>Simuler</th></tr></thead>
      <tbody>
      <?php foreach ($lignes as $l): ?>
        <?php $sel = (string) ($choix[$l['id']] ?? ''); $interdit = $l['justifiee'] === null; ?>
        <tr>
          <td><a href="<?= site_url('anomalies/' . $l['id']) ?>"><b><?= esc($l['regle_code']) ?></b></a></td>
          <td><?= ui_badge('gravite', $l['severite']) ?></td>
          <td><?= esc($l['message']) ?></td>
          <td class="num">-<?= esc(ui_nombre($l['corrigee']['gain'])) ?> <span class="mini">(→ <?= esc(ui_nombre($l['corrigee']['score'])) ?>)</span></td>
          <td class="num"><?php if ($interdit): ?><span class="mini">non autorisé</span><?php else: ?>-<?= esc(ui_nombre($l['justifiee']['gain'])) ?> <span class="mini">(→ <?= esc(ui_nombre($l['justifiee']['score'])) ?>)</span><?php endif; ?></td>
          <td>
            <select name="choix[<?= (int) $l['id'] ?>]" aria-label="Simulation pour l'anomalie <?= (int) $l['id'] ?>">
              <option value="">Ne rien faire</option>
              <option value="corrigee"<?= $sel === 'corrigee' ? ' selected' : '' ?>>Corriger</option>
              <option value="justifiee"<?= $sel === 'justifiee' ? ' selected' : '' ?><?= $interdit ? ' disabled' : '' ?>>Justifier<?= $interdit ? ' (non autorisé)' : '' ?></option>
            </select>
            <?php if ($interdit): ?><div class="raison">Une anomalie bloquante ne peut être justifiée que par un responsable fiscal ou un administrateur<?= $estComptable ? ' : corrigez-la ou demandez-lui.' : '.' ?></div><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p style="margin:14px 0 0"><button class="btn" type="submit">Simuler</button>
      <span class="mini" style="margin-left:10px">Simulation : rien n'est enregistré. Les gains sont calculés anomalie par anomalie ; leur cumul peut différer (plafond à 100, majoration historique).</span></p>
  </form>
  <?php endif; ?>
</div>

<?php endif; ?>
<?= $this->endSection() ?>
