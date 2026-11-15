# Présentation au professeur : moteur de déclaration fiscale (TVA, IRSA, retenues)

Binôme : Tsiory et Olivier. CodeIgniter 4, PHP, MySQL. Cadre juridique : Madagascar.
Durée conseillée : 25 minutes de présentation, puis les questions.

## 1. Déroulé et répartition

| Durée | Partie | Qui parle | Qui manipule |
|---|---|---|---|
| 2 min | Problème et objectif | Tsiory | - |
| 3 min | Architecture et schéma de données | Tsiory | - |
| 4 min | Les 6 règles de contrôle | Tsiory (R1, R2, R6), Olivier (R3, R4, R5) | - |
| 2 min | Le score de risque | Tsiory | - |
| 2 min | Le workflow de validation | Olivier | - |
| 9 min | Démonstration en direct | Olivier commente, Tsiory complète | Olivier |
| 2 min | Choix assumés et limites | Tsiory et Olivier | - |
| 1 min | Conclusion et évolutions | Tsiory | - |

## 2. Contenu de chaque partie

### 2.1 Problème et objectif (Tsiory)
- Une entreprise doit déclarer TVA, IRSA et retenues à la source ; les erreurs coûtent des redressements.
- Notre moteur lit les écritures comptables, les bordereaux et l'historique des contrôles, détecte les anomalies, calcule un score de risque expliqué, et bloque la validation tant qu'une anomalie grave reste ouverte.
- Sujet du professeur : schéma des données, catalogue de 6 règles, workflow de validation, score de risque, tableau de bord.

### 2.2 Architecture et schéma de données (Tsiory)
- 23 tables en base (sociétés, périodes fiscales, plan comptable, tiers, écritures et lignes, employés et bulletins, documents et données extraites, déclarations et lignes, règles, anomalies et preuves, scores, historique des contrôles, validations, journal d'audit, utilisateurs et rôles).
- Règle de conception : taux de TVA, barème IRSA et seuils légaux sont en base, avec une date d'effet et une colonne « vérifié ». Rien n'est écrit en dur dans le code.
- Convention : pas de logique métier dans les contrôleurs ni les vues. Un contrôleur appelle un service, le service renvoie un tableau, la vue l'affiche.

### 2.3 Les 6 règles

| Règle | Contrôle | Anomalie de démonstration |
|---|---|---|
| R1 TVA collectée | TVA comptabilisée conforme au taux en vigueur | VT-002 : 300 000 Ar au lieu de 400 000 |
| R2 TVA déductible | pièce justificative présente, NIF valide, pas de doublon | AC-002 sans justificatif ; AC-005 doublon d'AC-001 |
| R3 Base IRSA | masse salariale comptabilisée = somme des bruts des bulletins (tolérance paramétrable) | OD-003 : 1 750 000 contre 1 800 000 |
| R4 IRSA recalculé | IRSA retenu = IRSA du barème à la date, avec minimum de perception | bulletin de Rakoto : 20 000 au lieu de 25 700 |
| R5 Retenue à la source | retenue = base x taux, pour les services payés à des non-résidents | AC-004 : 100 000 au lieu de 200 000 |
| R6 Périodes | écritures dans la bonne période, équilibrées (débit = crédit) | OD-001 déséquilibrée ; OD-002 datée du 30/09 |

Seule R6 est de gravité « bloquante ».

### 2.4 Le score de risque (Tsiory)
- Points d'une anomalie = points de sa gravité (info 0, moyenne 1, élevée 2, bloquante 3) x poids de la règle x coefficient de son statut (ouverte 1, justifiée 0,5, corrigée 0, ignorée 0).
- Score = plafonné à 100 de (multiplicateur x somme des points), plus 10 si la société a déjà subi un redressement sur une règle qui se déclenche aujourd'hui.
- Niveaux : moins de 25 faible, 25 à 49 moyen, 50 à 74 élevé, 75 et plus critique.
- Exemple de la démonstration : R1 3 + R2 8 + R3 1 + R4 3 + R5 4 + R6 6 = 25 points, x 3 = 75, plus 10 d'historique = 85 / 100, critique.
- Le score est expliqué : l'écran « Pourquoi 85 / 100 ? » détaille chaque règle.
- [À valider avec Tsiory avant la soutenance : le multiplicateur retenu est 3 dans le code. Avec 5, les 25 points donneraient 125, plafonnés à 100, et la démonstration perdrait ses nuances.]

### 2.5 Le workflow (Olivier)
- Montrer `docs/diagramme_workflow.svg`.
- Cinq statuts plus « à corriger » ; cinq actions ; un rôle par action.
- Blocage : tant qu'une anomalie bloquante est ouverte, on ne peut ni soumettre ni valider.
- Séparation des tâches : qui a soumis ne peut pas valider.
- Chaque transition est tracée dans l'historique des validations et dans le journal d'audit à chaîne de hash.

## 3. Démonstration en direct (9 minutes)

Préparation, 10 minutes avant : `./demo_preparer.sh` (doit afficher 85 et 0), `.env` en `CI_ENVIRONMENT = production`, deux navigateurs ouverts (comptable et responsable fiscal), le terminal prêt dans le dossier du projet, les PDF dans `docs/pdf_demo/`.

Les étapes W01 à W26 sont détaillées dans `workflow.md`. Pour la soutenance, on joue cette version courte.

| Min | Étape | Qui clique | Ce qu'on dit |
|---|---|---|---|
| 0:00 | W01 : tableau de bord de la société à risque | Olivier (comptable) | « Score 85, critique, validation bloquée par 2 anomalies bloquantes. » Montrer jauge, courbe, carte de chaleur |
| 1:00 | W26 : société propre | Olivier | « Même moteur, score 0, validation possible. » Revenir à la société à risque |
| 1:30 | W03 : anomalie R5 et dossier de preuve | Olivier | « Le constat, la base légale et les lignes d'écriture sources. » |
| 2:30 | B1 : saisie d'un bordereau (bordereau_oct2026.pdf, 650 000, 12/10/2026, 5000000022) | Olivier | « Saisie assistée : le PDF est gardé avec son empreinte, trois champs sont enregistrés. L'extraction automatique est prévue en évolution. » Puis B3 : doublon refusé |
| 3:30 | W06 : générer la TVA, soumission impossible | Olivier | « Le bouton est grisé, avec le motif exact. » |
| 4:15 | W07 : justification de OD-002 par le responsable fiscal | Olivier (autre navigateur) | « Le comptable ne peut pas justifier une anomalie bloquante. Le score passe à environ 80,5. » |
| 5:00 | W09 et W10 : correction de OD-001 | Olivier | « La correction est refusée tant que la donnée est fausse. » Lancer `./demo_corriger_od001.sh` : score environ 71,5, bandeau vert |
| 6:00 | W11 puis W15 à W18 : soumettre, valider, déposer, archiver | Olivier (deux comptes) | « Chaque étape est réservée à un rôle ; le dépôt exige une référence de virement. » |
| 7:15 | W20 à W22 : poids de R2 de 2 à 4 avec aperçu | Olivier (responsable fiscal) | « L'aperçu montre le nouveau score sans rien enregistrer ; appliquer exige un motif. » |
| 8:15 | W24 et W25 : audit | Olivier (admin) | « Le journal est chaîné par hash ; `audit:verifier` confirme qu'il est intact. » |

Après la démonstration : `./demo_preparer.sh` pour tout remettre à zéro.

## 4. Choix assumés et limites (à dire honnêtement)

- **Pas d'extraction automatique du texte des PDF** : choix assumé pour la durée du projet. Les bordereaux se saisissent à la main avec le PDF joint ; l'extraction est prévue en évolution.
- **Valeurs légales** : taux de TVA, barème IRSA 2025, minimum de perception, retenue de 10 % et pénalités sont lus dans le Code des impôts (loi de finances 2025) ; le barème IRSA 2026 avec une tranche à 25 %, les cotisations CNaPS, OSTIE et FMFP, le salaire minimum et l'échéance du 15 du mois sont marqués « non vérifié » en base.
- **Bordereaux saisis** : enregistrés et consultables, mais aucune règle ne les lit encore.
- **R3** compare les bruts des bulletins au compte 641, sans vérifier la base imposable. **R5** ne couvre que les non-résidents, sur le compte 622. **R4** ne renvoie rien si aucun barème n'existe à la date.
- **Journal d'audit** : détecte la modification ou la suppression d'une ligne, mais pas la reconstruction complète de la chaîne sans conservation externe du dernier hash.
- **Connexion** : pas de blocage après plusieurs échecs ; les échecs sont tracés.
- **Déclarations générées** : elles reprennent les écritures telles quelles, y compris celles qui portent des anomalies ; c'est le workflow qui les bloque.
- **Réinitialisation de la démonstration** : elle ne restaure pas les données sources ; nous utilisons une copie de la base de référence.

## 5. Preuves de qualité à citer
- Recette automatique : 59 vérifications réussies, 0 échec (pages des 3 rôles, saisie valide, 7 saisies invalides refusées, droits par rôle).
- Test croisé des règles R3, R4 et R5 : 20 scénarios, 20 conformes (tolérances, arrondi, personnes à charge, minimum de perception, limites connues).
- Journal d'audit vérifié par `php spark audit:verifier`.

## 6. Questions probables et réponses

| Question | Réponse |
|---|---|
| Pourquoi pas d'extraction automatique des PDF ? | Choix de périmètre sur une semaine. La saisie assistée garde le PDF et son empreinte SHA-256 ; l'extraction (OCR) est l'évolution prévue. |
| Vos taux sont-ils exacts ? | Une partie est lue dans le Code des impôts (colonne « vérifié » à 1, article cité). Le reste est marqué « non vérifié » et n'est pas présenté comme certain. |
| Que se passe-t-il quand un taux change ? | On ajoute une ligne avec une date d'effet ; les contrôles lisent le taux en vigueur à la date de l'écriture. Aucun changement de code. |
| Pourquoi un multiplicateur de 3 ? | Pour laisser plus de nuances ; avec 5 le score sature très vite. [Confirmer la réponse avec Tsiory.] |
| Comment ajoute-t-on une règle ? | Une classe qui hérite de `RegleControle` et une ligne dans la table des règles ; le moteur, le score et le tableau de bord la prennent en compte. |
| Qu'est-ce qui empêche de contourner une anomalie bloquante ? | Seuls le responsable fiscal et l'admin peuvent la justifier ou l'ignorer, avec un commentaire obligatoire, et chaque action est tracée dans le journal d'audit. |
| Quelle sécurité ? | Connexion obligatoire sur toutes les pages et l'API, rôles, jeton CSRF, texte échappé à l'affichage, fichiers hors du dossier public, journal d'audit chaîné. Limite : pas de blocage après plusieurs échecs de connexion. |
| Comment avez-vous testé ? | Recette automatique (59 vérifications), test croisé des règles (20 scénarios), répétitions complètes à deux. |
| Pourquoi le comptable ne peut-il pas tout faire ? | Séparation des tâches : celui qui prépare et soumet n'est pas celui qui valide et dépose. |
| Les bordereaux saisis servent à quoi ? | Ils sont archivés avec leur empreinte et leurs trois champs. Les lier à une règle est une évolution identifiée. |

## 7. Si quelque chose tombe en panne
- Page blanche ou erreur : `tail -n 30 writable/logs/log-$(date +%F).log`, puis `./demo_preparer.sh`.
- Graphiques absents : vérifier que `public/assets/js/chart.umd.min.js` existe (Chart.js est servi en local, sans internet).
- Score différent de 85 au départ : `./demo_preparer.sh`, qui restaure la base de référence.
- Prévoir des captures d'écran de chaque étape dans un dossier de secours.

## 8. Liste de contrôle

La veille :
- [ ] `./demo_preparer.sh figer` exécuté sur une base propre et `writable/base_propre.sql` sauvegardé à part
- [ ] `./recette.sh` : 0 KO
- [ ] graphiques affichés sans connexion internet
- [ ] deux répétitions complètes jouées à deux, chronométrées
- [ ] captures d'écran de secours enregistrées
- [ ] ordinateur chargé, projecteur et adaptateur testés

Le jour J, 10 minutes avant :
- [ ] `./demo_preparer.sh` : 85 et 0
- [ ] `.env` en production, serveur lancé
- [ ] deux navigateurs connectés (comptable et responsable fiscal), admin prêt
- [ ] terminal ouvert dans le dossier du projet
- [ ] zoom du navigateur à 110 ou 125 %
