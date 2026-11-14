<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion - Moteur de déclaration fiscale</title>
<style>
body{margin:0;font:15px/1.5 -apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f3f5f9;color:#1d2733}
.boite{max-width:400px;margin:9vh auto;background:#fff;border:1px solid #dbe1ea;border-radius:12px;padding:28px}
h1{font-size:21px;color:#1f3a5f;margin:0 0 4px}
p.s{color:#5b6878;margin:0 0 20px}
label{display:block;font-size:13px;color:#5b6878;margin:12px 0 4px}
input{width:100%;padding:10px;border:1px solid #dbe1ea;border-radius:6px;font:inherit}
button{margin-top:20px;width:100%;padding:11px;border:0;border-radius:6px;background:#1f3a5f;color:#fff;font:inherit;font-weight:600;cursor:pointer}
.flash{padding:10px 14px;border-radius:8px;margin-bottom:14px;border:1px solid}
.erreur{background:#ffebee;border-color:#ef9a9a;color:#b71c1c}.info{background:#e3f2fd;border-color:#90caf9;color:#0d47a1}
.aide{margin-top:18px;font-size:12.5px;color:#5b6878;border-top:1px solid #dbe1ea;padding-top:12px}
</style>
</head>
<body>
<div class="boite">
  <h1>Moteur de déclaration fiscale</h1>
  <p class="s">TVA, IRSA et retenues : connexion</p>
  <?php foreach (['erreur', 'info'] as $t): if (session()->getFlashdata($t)): ?>
  <div class="flash <?= $t ?>"><?= esc(session()->getFlashdata($t)) ?></div>
  <?php endif; endforeach; ?>
  <form method="post" action="<?= site_url('connexion') ?>">
    <?= csrf_field() ?>
    <label for="email">Adresse e-mail</label>
    <input id="email" type="email" name="email" value="<?= esc(old('email') ?? '', 'attr') ?>" required autofocus>
    <label for="mdp">Mot de passe</label>
    <input id="mdp" type="password" name="mot_de_passe" required>
    <button type="submit">Se connecter</button>
  </form>
  <div class="aide">Comptes de démonstration (mot de passe <b>Demo@2026</b>) : comptable@demo.mg, fiscal@demo.mg, admin@demo.mg</div>
</div>
</body>
</html>
