# Moteur de déclaration fiscale (TVA, IRSA, retenues) : où en est le projet

Projet en binôme (Tsiory et Olivier), CodeIgniter 4, PHP, MySQL, cadre juridique malgache.
Ce document est à lire en premier : il dit ce qui est fait, comment le faire tourner, et ce qu'il reste à faire de ton côté.

À placer à la racine du projet.

---

## 1. En une minute

- **Le moteur est terminé et testé** (partie de Tsiory) : base de données, 6 règles de contrôle, score de risque expliqué, workflow de validation, journal d'audit, données du dashboard, règles paramétrables, deux sociétés de démonstration.
- **Il ne reste presque que l'interface** (ta partie) : connexion, écrans, graphiques. Tout ce dont les écrans ont besoin existe déjà sous forme de **services PHP** et d'**URL JSON** : un écran revient surtout à de l'affichage.
- Rien n'est à recalculer dans l'interface : le score, les anomalies, les droits et les refus sont décidés par les services.

---

## 2. Installer et vérifier

Prérequis : PHP 8.2 ou plus (CodeIgniter 4.7), MySQL ou MariaDB.

1. Créer une base (par exemple `impots_fiscale`, en `utf8mb4`) et renseigner le `.env` :
   ```
   database.default.hostname = 127.0.0.1
   database.default.database = impots_fiscale
   database.default.username = root
   database.default.password =
   database.default.DBDriver = MySQLi
   ```
   Le fichier doit s'appeler `.env` (avec le point). Ne pas le mettre sur Git.
2. Créer les tables et les données :
   ```
   php spark migrate
   php spark db:seed DatabaseSeeder
   ```
   Lancer `db:seed` **une seule fois** par base : les seeders ne vérifient pas les doublons. En cas de doute, supprimer la base et la recréer.
3. Ajouter les routes dans `app/Config/Routes.php` (après la route `/`) :
   ```php
   $routes->get('api/dashboard/(:num)/(:num)', 'DashboardApi::donnees/$1/$2');
   $routes->get('api/regles', 'ParametrageApi::lister');
   $routes->post('api/regles/(:num)/apercu', 'ParametrageApi::apercu/$1');
   $routes->post('api/regles/(:num)/appliquer', 'ParametrageApi::appliquer/$1');
   ```
4. Vérifier que tout marche :
   ```
   php spark demo:reinitialiser
   ```
   Résultat attendu :

   | Id | Société | Anomalies | Score |
   |---|---|---|---|
   | 1 | Société Démo impots_fiscale SARL (à risque) | 8, dont 2 bloquantes | 85 / 100, critique |
   | 2 | Société Modèle Claritas SA (propre) | 0 | 0 / 100, faible |

Si les chiffres sont différents, ne pas continuer : recréer la base et redemander à Tsiory.

### Comptes de démonstration (mot de passe : `Demo@2026`)

| E-mail | Rôle |
|---|---|
| comptable@demo.mg | comptable |
| fiscal@demo.mg | responsable_fiscal |
| admin@demo.mg | admin |

---

## 3. Commandes utiles

| Commande | Rôle |
|---|---|
| `php spark demo:reinitialiser` | Remet toute la démo à zéro (à faire avant chaque répétition et le jour J) |
| `php spark regles:executer 2026 10 [--societe 2]` | Calcule les anomalies d'une période |
| `php spark score:calculer 2026 10 [--societe 2]` | Calcule et enregistre le score, avec son explication |
| `php spark dashboard:donnees 2026 10 [json] [--societe 2]` | Affiche les données du dashboard |
| `php spark workflow:demo` | Déroule tout le workflow (génération, blocage, correction, validation, dépôt, archivage) |
| `php spark regles:parametrer R2 poids=4 --apercu` | Simule la modification d'une règle sans rien enregistrer |
| `php spark regles:parametrer R2 poids=4 --motif "..."` | Applique la modification |
| `php spark audit:verifier` | Vérifie que le journal d'audit n'a pas été modifié |

`workflow:demo` modifie les anomalies et crée une déclaration : relancer `demo:reinitialiser` ensuite.

---

## 4. Où est quoi

```
app/
  Config/Score.php                    paramètres du score (multiplicateur 3, seuils 25, 50, 75)
  Libraries/
    Regles/                           le moteur : RuleEngine, RegleControle, ContexteControle, R1 à R6
    Score/CalculScore.php             calcul du score expliqué
    Workflow/                         WorkflowService, DeclarationService, AuditService, WorkflowException
    Dashboard/DashboardService.php    données du dashboard
    Parametrage/                      ParametrageRegles, ParametrageException
  Controllers/                        DashboardApi, ParametrageApi (les seuls contrôleurs existants)
  Commands/                           les commandes du tableau ci-dessus
  Models/                             un modèle par table (23)
  Database/Migrations/                23 migrations
  Database/Seeds/                     seeders et jeux de démonstration
```

Convention : **ne pas mettre de logique métier dans les contrôleurs ou les vues**. Un contrôleur appelle un service, un service renvoie un tableau, la vue l'affiche.

---

## 5. Les services à appeler depuis les écrans

### Moteur et score
```php
(new \App\Libraries\Regles\RuleEngine())->executer($societeId, $periodeId);
// recalcule les anomalies ouvertes de la période et leurs preuves (idempotent)

$res = (new \App\Libraries\Score\CalculScore())->calculer($societeId, $periodeId);
// ['score' => 85.0, 'niveau' => 'critique', 'facteur_historique' => 10.0, 'detail' => [...], 'id' => 7]
```
Le détail (`$res['detail']`) contient, par règle : nombre d'anomalies, poids, points, contribution au score. C'est l'écran « pourquoi 85 / 100 ? ».

### Workflow d'une déclaration
```php
$wf = new \App\Libraries\Workflow\WorkflowService();

// Quels boutons afficher ou griser, avec le motif de refus :
$wf->actionsPossibles($declarationId, $utilisateurId);
// [['action' => 'valider', 'vers' => 'validee', 'autorisee' => true, 'raison' => null], ...]

// Exécuter une action (lève WorkflowException avec un message lisible si refusée) :
$wf->transitionner($declarationId, 'soumettre', $utilisateurId);
$wf->transitionner($declarationId, 'renvoyer', $utilisateurId, 'Justifier AC-002.');
$wf->transitionner($declarationId, 'deposer', $utilisateurId, null, ['reference_virement' => 'VIR-2026-10-0001']);

// Générer une déclaration (brouillon) :
(new \App\Libraries\Workflow\DeclarationService())->generer($societeId, $periodeId, 'TVA', $utilisateurId);
// types : 'TVA', 'IRSA', 'RETENUE'
```
Actions : `soumettre`, `renvoyer`, `valider`, `deposer`, `archiver`. Afficher le message de `WorkflowException` tel quel à l'utilisateur.

```
brouillon --soumettre--> a_valider --valider--> validee --deposer--> deposee --archiver--> archivee
     ^                       |
     +---- a_corriger <------+   (renvoyer, commentaire obligatoire)
```

| Action | Statut de départ | Rôle autorisé | Conditions |
|---|---|---|---|
| soumettre | brouillon, a_corriger | comptable, admin | aucune anomalie bloquante ouverte |
| renvoyer | a_valider | responsable_fiscal, admin | commentaire obligatoire |
| valider | a_valider | responsable_fiscal, admin | aucune anomalie bloquante ouverte ; ne peut pas valider ce qu'on a soumis soi-même |
| deposer | validee | responsable_fiscal, admin | référence de virement obligatoire |
| archiver | deposee | responsable_fiscal, admin | - |

L'historique des validations est dans la table `validations` (étape, décision, commentaire, utilisateur, date) : c'est l'écran « historique ».

### Dashboard
```php
(new \App\Libraries\Dashboard\DashboardService())->donnees($societeId, $periodeId);
```
Ou, en JSON : `GET /api/dashboard/{societe_id}/{periode_id}` (société 1, période 3 : société à risque ; société 2, période 6 : société propre. Vérifier les ids dans la base).

Le tableau contient : `score` (valeur, niveau, variation, détail), `kpi`, `par_regle`, `par_gravite`, `par_statut`, `par_impot`, `top_anomalies`, `evolution_score`, `echeances` (avec alerte à 7 jours ou moins) et un bloc `graphiques` **déjà au format Chart.js** (labels et données), dont la heatmap règle par période.

Exemple Chart.js :
```js
fetch('/api/dashboard/1/3').then(r => r.json()).then(d => {
  new Chart(document.getElementById('evolution'), {
    type: 'line',
    data: { labels: d.graphiques.evolution_score.labels,
            datasets: [{ label: 'Score de risque', data: d.graphiques.evolution_score.data }] }
  });
});
```
Jauge : `d.score.valeur` (0 à 100) et `d.score.niveau` pour la couleur. Validation bloquée : `d.kpi.validation_bloquee`.

### Paramétrage des règles
```php
$p = new \App\Libraries\Parametrage\ParametrageRegles();
$p->lister();
$p->previsualiser($regleId, ['poids' => 4], $societeId, $periodeId);                  // simule, n'enregistre rien
$p->appliquer($regleId, ['poids' => 4], 'Risque accru', $utilisateurId, $societeId, $periodeId);
```
Champs modifiables : `poids` (0 à 10), `gravite` (info, moyenne, elevee, bloquante), `active` (vrai ou faux), et les seuils numériques ou booléens de `parametres` (par exemple `['parametres' => ['tolerance_absolue' => 200]]`). Le motif est obligatoire ; seuls `responsable_fiscal` et `admin` peuvent modifier ; désactiver ou déclasser une règle bloquante est réservé à `admin`. Les refus lèvent `ParametrageException` (message à afficher).

En JSON : `GET /api/regles`, `POST /api/regles/{id}/apercu`, `POST /api/regles/{id}/appliquer`, avec le corps
`{"modifs": {...}, "societe_id": 1, "periode_id": 3, "motif": "...", "utilisateur_id": 2}`. Erreur de validation : code 422 avec `{"erreur": "..."}`.
L'aperçu renvoie le score avant et après : il sert à un curseur qui met à jour la jauge en direct.

### Journal d'audit
```php
(new \App\Libraries\Workflow\AuditService())->journaliser($utilisateurId, 'action', 'table', $id, ['avant' => '...'], ['apres' => '...']);
```
Les valeurs avant et après ne doivent contenir que des chaînes, des entiers ou `null` (pas de nombres à virgule). À appeler pour toute action sensible que tu ajoutes (justification d'une anomalie, par exemple).

---

## 6. Les données utiles pour les écrans

| Besoin | Table | Champs importants |
|---|---|---|
| Liste et détail des anomalies | `anomalies` (+ `regles_controle`) | `regle_id`, `severite`, `statut`, `montant_concerne`, `message`, `action_corrective`, `reference_type`, `reference_id`, `justification` |
| Dossier de preuve d'une anomalie | `anomalie_preuves` | `anomalie_id`, `table_source`, `enregistrement_id`, `libelle`, `montant` |
| Score et son explication | `scores_risque` | `score`, `niveau`, `facteur_historique`, `detail` (JSON), `calcule_le` |
| Déclarations | `declarations`, `declaration_lignes` | `type`, `statut`, `montant_total`, `date_echeance`, `reference_virement` |
| Historique des validations | `validations` | `etape`, `decision`, `commentaire`, `utilisateur_id`, `created_at` |
| Saisie des bordereaux | `documents`, `donnees_extraites` | fichier, champs lus (`montant_ttc`, `date_document`, `nif_emetteur`) |
| Historique des contrôles fiscaux | `controles_fiscaux_historique` | `date_controle`, `type_impot`, `regle_code`, `motif`, `redressement_montant` |
| Utilisateurs et rôles | `utilisateurs`, `roles` | `email`, `mot_de_passe` (hash `password_hash`), `role_id`, rôles `comptable`, `responsable_fiscal`, `admin` |

Statuts d'une anomalie : `ouverte`, `justifiee`, `corrigee`, `ignoree`. Seule `ouverte` pèse en plein dans le score (justifiée : moitié, corrigée et ignorée : zéro) et une anomalie **bloquante ouverte** empêche de soumettre et de valider.

Les modèles CI4 existent pour chaque table (`app/Models/`), par exemple `AnomalieModel`, `DeclarationModel`, `ValidationModel`.

---

## 7. Ce qu'il reste à faire (ta partie), dans l'ordre conseillé

1. **Installer et vérifier** (section 2) et relire une fois le moteur : lancer les commandes, regarder les anomalies de la démo. Faire le test croisé de R3, R4 et R5 en modifiant des données pour voir si elles se déclenchent comme prévu.
2. **Authentification et rôles** : connexion avec la table `utilisateurs` (`password_verify`), session contenant l'id et le rôle, filtre de protection des pages et des URL JSON. **L'identifiant utilisateur doit venir de la session, jamais du navigateur** : aujourd'hui les URL JSON de paramétrage le lisent dans le corps de la requête, uniquement parce que la connexion n'existe pas encore.
3. **Anomalies** : liste (filtres par règle, gravité, statut), détail avec dossier de preuve (`anomalie_preuves`) et message d'action corrective.
4. **Justification d'une anomalie** : passer une anomalie en `justifiee` ou `corrigee` avec un commentaire, en enregistrant `traitee_par` et une ligne dans le journal d'audit. **Il n'existe pas encore de service pour cela** : le faire dans un petit `AnomalieService` (ou demander à Tsiory de le générer) plutôt que de mettre des `UPDATE` dans le contrôleur. Après chaque traitement, appeler `RuleEngine::executer` puis `CalculScore::calculer` pour que le score se mette à jour.
5. **Déclarations et validation** : liste, détail avec lignes, boutons déduits de `actionsPossibles`, saisie du commentaire et de la référence de virement, historique des validations. Afficher clairement le blocage et son motif.
6. **Dashboard** avec Chart.js (voir section 5) : jauge, évolution du score, anomalies par règle et par gravité, heatmap, plus grosses anomalies, échéances.
7. **Écran de paramétrage des règles** : liste, curseurs de poids, activation, tolérances, champ motif, aperçu en direct.
8. **Saisie des bordereaux** (formulaire avec le PDF joint, rempli dans `documents` et `donnees_extraites`) et de l'historique des contrôles fiscaux. L'extraction automatique des PDF n'est pas prévue pour cette version : le dire dans le rapport comme un choix assumé.
9. **Rapport** : diagramme du workflow et description du dashboard (Tsiory rédige le schéma de données, le catalogue de règles et la formule du score).
10. **Bonus si le temps reste** : export PDF de la déclaration, alertes d'échéance (les données sont déjà dans `echeances`), comparaison du score entre périodes (déjà dans `evolution_score`).

Le cœur du projet passe avant les bonus : si le temps manque, on sacrifie les bonus.

---

## 8. Scénario de soutenance

1. `php spark demo:reinitialiser`.
2. Dashboard de la société propre (score 0), puis de la société à risque (score 85, 8 anomalies, validation bloquée).
3. Ouvrir une anomalie et son dossier de preuve.
4. Soumettre la déclaration : refus (2 anomalies bloquantes). Corriger ou justifier, resoumettre.
5. Valider avec un autre utilisateur, déposer avec la référence de virement, archiver.
6. Modifier le poids d'une règle avec l'aperçu : le score change en direct.
7. `php spark audit:verifier` : journal intact.

Répéter ce scénario à deux avant le jour J.

---

## 9. Ce qui est connu et pas encore vérifié

À dire honnêtement dans le rapport, sous forme de limites et d'évolutions.

- **Valeurs légales** : taux de TVA, barème IRSA 2025, minimum de perception, retenue de 10 %, pénalités : lus dans le Code des impôts suivant la LFI 2025 (colonne `verifie = 1`, article indiqué dans `source_legale`).
- **Non vérifié** (`verifie = 0`) : barème IRSA 2026 avec la tranche à 25 % au-delà de 4 000 000 Ar (sources secondaires, texte officiel à lire), cotisations CNaPS, OSTIE, FMFP, salaire minimum, échéance du 15 du mois (Code des procédures fiscales, non lu).
- **R2** ne contrôle pas le paiement bancaire exigé pour déduire la TVA (pas de données de paiement) et repère les doublons par `numero_piece`.
- **R5** ne couvre que les paiements à des non-résidents ; l'impôt synthétique et l'IRSA reversé viennent après lecture du Code des procédures fiscales.
- **Journal d'audit** : il détecte la modification ou la suppression d'une ligne, mais pas une reconstruction complète de la chaîne sans ancrage externe du dernier hash.
- **Les déclarations générées** reprennent les écritures telles quelles, y compris celles qui portent des anomalies : c'est le rôle du workflow de les bloquer.
- **Les URL JSON ne sont pas protégées** tant que l'authentification n'existe pas.

---

## 10. Règles de travail à deux

- Une branche Git par fonctionnalité, fusion après relecture par l'autre. Pas de poussée directe sur la branche principale.
- **Toute modification du schéma de base est annoncée à l'autre avant** : les migrations sont à Tsiory. Ne pas modifier une migration existante.
- Les taux, barèmes et seuils légaux restent **en base**, jamais dans le code ni dans les vues.
- **Copier des fichiers, jamais remplacer un dossier entier** quand on intègre un lot de fichiers : un dossier remplacé en entier fait disparaître les fichiers voisins (c'est déjà arrivé une fois avec `app/Libraries/Workflow/`).
- Point de 10 minutes chaque jour : ce qui est fait, ce qui bloque, ce qui dépend de l'autre.
- Après tout changement : `php spark demo:reinitialiser`, vérifier 85 et 0, puis `php spark audit:verifier`.

---

## 11. Lexique

| Terme | Sens |
|---|---|
| Code des impôts | Texte de loi malgache d'où viennent les taux, le barème et les articles cités dans les règles |
| IRSA | Impôt sur les revenus salariaux et assimilés, retenu sur les bulletins de paie |
| Anomalie | Écart détecté par une règle sur une écriture, un bulletin ou une période |
| Bloquante | Gravité la plus haute : empêche de soumettre et de valider tant qu'elle est ouverte |
| Dossier de preuve | Les lignes sources d'une anomalie (écritures, bulletins) |
| Facteur historique | +10 au score si la société a déjà eu un redressement sur une règle qui se déclenche aujourd'hui |
| NIF | Numéro d'identification fiscale du tiers |
