# Ce que Tsiory doit faire après le push d'Olivier

Branche à relire : `feature/saisie-olivier`. Ne pas pousser directement sur la branche principale : relire, tester, puis fusionner.

## 1. Ce qu'Olivier a ajouté ou modifié

Aucune migration n'a été touchée (à vérifier à l'étape 2).

| Type | Fichiers |
|---|---|
| Nouveaux (saisie) | `app/Libraries/Saisie/` (SaisieException, Valeurs, BordereauService, ControleHistoriqueService), `app/Controllers/Bordereaux.php`, `app/Controllers/Controles.php`, `app/Views/bordereaux/liste.php`, `app/Views/controles/liste.php` |
| Modifiés | `app/Config/Routes.php` (5 routes ajoutées dans le groupe protégé), `app/Views/layout.php` (2 liens de menu, style des champs date et fichier), `app/Views/tableau.php` (Chart.js servi en local), `.gitignore` |
| Nouveau fichier statique | `public/assets/js/chart.umd.min.js` (Chart.js 4.4.1) |
| Outils | `recette.sh`, `tests_r3r4r5.sh`, `demo_preparer.sh`, `demo_corriger_od001.sh` |
| Documents | `workflow.md`, `presentation.md`, `olivier.md`, `docs/rapport_olivier.md`, `docs/diagramme_workflow.svg`, `docs/scenario_demo.md`, `docs/creer_pdfs_demo.py`, `docs/tests/` (résultats des tests) |

## 2. Récupérer et vérifier la branche

    git fetch origin
    git checkout feature/saisie-olivier
    git diff --stat main...feature/saisie-olivier
    git diff --name-only main...feature/saisie-olivier -- app/Database

(remplacer `main` par `master` si besoin). La dernière commande ne doit rien afficher : aucune migration ni aucun seeder modifié.

## 3. Installer sur ta machine

Les scripts utilisent par défaut `/opt/lampp/bin/php` et `/opt/lampp/bin/mysql`. Sur une autre installation, indiquer les chemins :

    export PHP=$(which php) MYSQL=$(which mysql) DUMP=$(which mysqldump)

Créer une base propre, renseigner `.env`, puis :

    php spark migrate
    php spark db:seed DatabaseSeeder
    ./demo_preparer.sh figer
    python3 docs/creer_pdfs_demo.py

Attendu : 85 / 100 pour la société à risque, 0 / 100 pour la société propre. Si ce n'est pas le cas, ne pas continuer et prévenir Olivier.

## 4. Relire le code (par fichier)

| Fichier | À vérifier |
|---|---|
| `BordereauService.php` | contrôle du fichier (extension, signature `%PDF-`, 5 Mo, doublon par SHA-256), rangement hors de `public/`, nom aléatoire, transaction document plus 3 champs, suppression du fichier si l'enregistrement échoue, appel de l'audit avec des chaînes uniquement |
| `ControleHistoriqueService.php` | règle et type d'impôt validés, montant en chaîne décimale, date non future |
| `Valeurs.php` | lecture des montants (pas de flottant enregistré), dates réelles |
| `Bordereaux.php` et `Controles.php` | aucun métier dans le contrôleur, droits (`exiger`), jeton CSRF, redirections avec message, service du PDF avec en-têtes sûrs |
| Vues | texte échappé avec `esc()`, champs `old()` après refus |
| `Routes.php` | les 5 routes sont dans le groupe `csrf` plus `AuthFilter` |

Rappel de la convention : les taux et seuils restent en base, jamais dans le code ni dans les vues.

## 5. Lancer les tests

    ./recette.sh 2>&1 | tee resultats_recette.txt
    ./demo_preparer.sh
    ./tests_r3r4r5.sh 2>&1 | tee resultats_tests.txt
    ./demo_preparer.sh
    php spark audit:verifier

Attendu : recette « 0 KO » (59 vérifications), 20 scénarios conformes pour R3, R4 et R5, journal intact.

Puis jouer à la main les sections 2, 3 et 4 de `workflow.md` (relecture croisée : c'est toi qui testes la saisie d'Olivier, comme il a testé R3, R4 et R5). Noter chaque écart dans la fiche de suivi.

## 6. Décisions à prendre avec Olivier

| Sujet | Situation | Proposition |
|---|---|---|
| Multiplicateur du score | 3 dans `app/Config/Score.php`, 5 dans le cahier de répartition | Garder 3 (avec 5, les 25 points de la démo donneraient 125 plafonnés à 100) et corriger le cahier et le rapport |
| `demo:reinitialiser` | ne remet pas les données sources (écritures, bulletins, tiers, documents, historique) alors que le LISEZMOI dit « remet toute la démo à zéro » | Corriger le LISEZMOI et utiliser `demo_preparer.sh`, ou faire recharger les données de démonstration par la commande |
| Bordereaux et règles | les bordereaux saisis ne sont lus par aucune règle ; R3 compare les bulletins à la compta, alors que le cahier parle de la « base IRSA du bordereau » | Soit l'écrire comme limite dans le rapport, soit lier un document à une écriture dans le formulaire (R2 lit déjà `ecritures.document_id`) soit faire lire le bordereau par R3. Décision de Tsiory, il est propriétaire des règles |
| Schéma du workflow du LISEZMOI | montre « à corriger » qui retourne à « brouillon » ; le code fait « à corriger » vers « à valider » par l'action « soumettre » | Corriger le schéma du LISEZMOI (le diagramme d'Olivier suit le code) |
| Droits de saisie | bordereaux : comptable et admin ; historique des contrôles : responsable fiscal et admin | Confirmer ou modifier la constante `ROLES_SAISIE` des deux contrôleurs |
| Instruction du LISEZMOI sur les routes | demande de remplacer tout `Routes.php` | Préciser que `Routes.php` contient maintenant les routes de la saisie et ne doit plus être remplacé |

## 7. Fusionner

    git checkout main
    git merge --no-ff feature/saisie-olivier
    git push origin main

Après la fusion, refaire sur `main` : `php spark migrate`, `./demo_preparer.sh figer`, `./recette.sh`.

## 8. Ta partie du rapport et la relecture croisée

- Écrire : schéma de données (23 tables), catalogue des règles, formule du score (avec le multiplicateur décidé à l'étape 6).
- Relire `docs/rapport_olivier.md` : retirer la partie « Notes de travail » une fois les points de l'étape 6 réglés, et aligner la formule du score.
- Valeurs légales à lire dans le texte officiel (colonne `verifie = 0`) : barème IRSA 2026 (tranche à 25 % au-delà de 4 000 000 Ar), cotisations CNaPS, OSTIE, FMFP, salaire minimum, échéance du 15 du mois. Tant qu'elles ne sont pas lues, elles restent citées comme non vérifiées.

## 9. Répétition et gel du code

1. Répéter à deux la démonstration courte de `presentation.md`, section 3, au moins deux fois, chronométrée.
2. Passer `CI_ENVIRONMENT` à `production` dans le `.env` de la machine de soutenance.
3. Tester l'affichage des graphiques sans connexion internet.
4. Geler le code : plus aucune modification sauf correction d'un défaut bloquant.

    git tag v1.0-soutenance
    git push origin v1.0-soutenance

5. Sauvegarder hors du dépôt : un zip du projet, `writable/base_propre.sql` et un export de la base.

## 10. Bonus, seulement si le temps reste

Limitation des tentatives de connexion, export PDF de la déclaration, règle R5 élargie aux autres comptes et aux résidents soumis à retenue, règle qui lit les bordereaux saisis. Le cœur du projet passe avant les bonus.
