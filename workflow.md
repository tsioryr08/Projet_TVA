# Workflow de test détaillé (à jouer dans l'application)

Ce document sert à tester toute l'application à la main, avec des données précises à entrer. Les valeurs de score marquées `≈` sont calculées avec la formule du score (points = gravité x poids x statut, multiplicateur 3, plus 10 d'historique) : l'écran fait foi, tout écart est à noter dans la fiche de suivi.

## 0. Préparation

    cd /opt/lampp/htdocs/DLA/Mr_Tovo/Projet_TVA
    python3 docs/creer_pdfs_demo.py        (une seule fois)
    ./demo_preparer.sh                     (avant chaque passage : doit afficher 85 / 100 et 0 / 100)
    /opt/lampp/bin/php spark serve         (si le serveur n'est pas déjà lancé)

Ouvrir `http://localhost:8080/`. Deux navigateurs (ou une fenêtre privée) : un pour le comptable, un pour le responsable fiscal.

| Rôle | E-mail | Mot de passe |
|---|---|---|
| comptable | comptable@demo.mg | Demo@2026 |
| responsable fiscal | fiscal@demo.mg | Demo@2026 |
| admin | admin@demo.mg | Demo@2026 |

Société à risque : « Société Démo Fiscalia SARL », période 10/2026 (sélecteur en haut à droite).
Société propre : « Société Modèle Claritas SA ».

## 1. Données de test

### Fichiers PDF (dans `docs/pdf_demo/`)

| Fichier | Type | Montant TTC | Date | NIF émetteur | Usage |
|---|---|---|---|---|---|
| bordereau_oct2026.pdf | Bordereau | 650 000 | 2026-10-12 | 5000000022 | saisie valide |
| facture_fournisseur.pdf | Facture | 480 000 | 2026-08-10 | 5000000033 | date hors période |
| bordereau_irsa_oct2026.pdf | Bordereau | 1 250 000 | 2026-10-31 | 5000000044 | saisie avec espaces dans le montant |
| faux.pdf | - | - | - | - | refus : pas un vrai PDF |

### Textes à saisir

| Où | Texte |
|---|---|
| Justification d'une anomalie bloquante (responsable fiscal) | Écriture du 30/09 reclassée en octobre, validée par la direction |
| Justification d'une anomalie non bloquante | Retenue régularisée par avenant au contrat |
| Renvoi d'une déclaration | Justifier AC-002 avant validation |
| Référence de virement | VIR-2026-10-0001 |
| Motif de modification d'une règle | Risque accru sur les doublons de facture |
| Motif d'un contrôle fiscal | Écart de barème sur le minimum de perception |

## 2. Saisie des bordereaux

Compte : comptable. Page : Bordereaux. Société à risque, période 10/2026. (Le formulaire n'apparaît que si une période est sélectionnée.)

| N° | À entrer | Résultat attendu |
|---|---|---|
| B1 | Type Bordereau, tiers vide, montant `650 000`, date `12/10/2026`, NIF `5000000022`, fichier bordereau_oct2026.pdf | Bandeau vert « Bordereau enregistré (document n° …) » ; nouvelle ligne en haut du tableau avec 650 000 Ar |
| B2 | Cliquer le nom du fichier de la ligne B1 | Le PDF s'ouvre dans un nouvel onglet et affiche « Montant TTC : 650 000 Ar » |
| B3 | Refaire B1 avec le même fichier | Bandeau rouge « Ce PDF a déjà été enregistré pour cette société (même contenu). » ; rien ajouté |
| B4 | Mêmes données avec faux.pdf | Bandeau rouge « Ce fichier n'est pas un PDF valide. » |
| B5 | Montant `abc` (fichier facture_fournisseur.pdf) | Bandeau rouge « Montant TTC : saisir un montant en Ariary… » |
| B6 | Montant `-5` | Même refus |
| B7 | Date `01/01/2027` | « Date du document : la date ne peut pas être dans le futur. » |
| B8 | NIF `12` | « NIF de l'émetteur : 5 à 30 chiffres ou lettres, sans signe. » |
| B9 | Type Facture, montant `480 000`, date `10/08/2026`, NIF `5000000033`, fichier facture_fournisseur.pdf | Bandeau vert **et** bandeau bleu « La date du document (10/08/2026) est en dehors de la période 10/2026. » ; document enregistré |
| B10 | Type Bordereau, montant `1 250 000`, date `31/10/2026`, NIF `5000000044`, fichier bordereau_irsa_oct2026.pdf | Enregistré (les espaces du montant sont acceptés) |
| B11 | Se connecter en responsable fiscal, ouvrir Bordereaux | Liste visible, bandeau bleu « réservée au comptable », pas de formulaire |

Après un refus, les champs texte restent remplis ; seul le fichier est à joindre de nouveau.

## 3. Historique des contrôles fiscaux

Compte : responsable fiscal. Page : Contrôles. Un contrôle de démonstration est déjà présent.

| N° | À entrer | Résultat attendu |
|---|---|---|
| K1 | Date `10/09/2025`, impôt IRSA, règle R4, redressement `1 200 000`, motif « Écart de barème sur le minimum de perception » | Bandeau vert « Contrôle enregistré. Score de la période 10/2026 : … → … » ; score `≈` inchangé à 85 (le facteur historique de +10 est déjà acquis) ; nouvelle ligne dans le tableau |
| K2 | Motif `ok` | « Motif du redressement : entre 5 et 1000 caractères. » |
| K3 | Date `01/01/2027` | « la date ne peut pas être dans le futur » |
| K4 | Redressement `-100` | « Montant du redressement : saisir un montant en Ariary… » |
| K5 | Se connecter en comptable, ouvrir Contrôles | Liste visible, bandeau bleu « réservée au responsable fiscal », pas de formulaire |
| K6 | Passer à la société propre, refaire K1 | Le score reste à 0 : le facteur historique ne s'applique que si la règle se déclenche aujourd'hui |

## 4. Workflow complet d'une déclaration TVA

Les étapes se jouent dans l'ordre, sur la société à risque, période 10/2026, après `./demo_preparer.sh`.
Les actions impossibles sont des boutons grisés avec leur motif ; les actions qui exigent un commentaire ou une référence se refusent après le clic.

| Étape | Qui | Action et données | Résultat attendu |
|---|---|---|---|
| W01 | comptable | Tableau de bord | Score 85 / 100, niveau critique ; 8 anomalies dont 2 bloquantes ; bandeau rouge « Validation bloquée : 2 anomalie(s) bloquante(s) ouverte(s). » |
| W02 | comptable | Anomalies, filtre gravité « bloquante », statut « ouverte » | 2 lignes de la règle R6 (écritures OD-001 et OD-002) |
| W03 | comptable | Ouvrir l'anomalie R5 (écriture AC-004) | Constat « retenue de 100 000 au lieu de 200 000 », base légale, dossier de preuve, 4 actions |
| W04 | comptable | Justifier cette anomalie avec le commentaire `ok` | Refus : « Un commentaire de 10 caractères minimum est obligatoire pour « justifier ». » |
| W05 | comptable | Ouvrir OD-002 (R6), tenter « Justifier » | Bouton grisé : « Une anomalie bloquante ne peut être justifiée que par un responsable fiscal ou un administrateur » |
| W06 | comptable | Déclarations, « Générer la TVA », ouvrir la déclaration | Statut brouillon ; le bouton « soumettre » est grisé avec « 2 anomalie(s) bloquante(s) ouverte(s) : corrigez-les ou justifiez-les avant de continuer. » |
| W07 | responsable fiscal | Ouvrir OD-002, « Justifier » avec le texte de justification d'une anomalie bloquante | Succès ; score `≈ 80,5` ; une seule bloquante ouverte |
| W08 | responsable fiscal | Ouvrir OD-001, « Corriger » sans avoir touché aux données | Refus : « L'anomalie est toujours détectée sur les données actuelles… » |
| W09 | terminal | `./demo_corriger_od001.sh` | `lignes_modifiees = 1` |
| W10 | responsable fiscal | OD-001, « Corriger » | Succès ; score `≈ 71,5` ; bandeau du tableau de bord vert « la validation est possible » |
| W11 | comptable | Ouvrir la déclaration TVA, « Soumettre » | Statut « à valider » |
| W12 | responsable fiscal | « Renvoyer » sans commentaire | Refus : « Un commentaire est obligatoire pour cette action. » |
| W13 | responsable fiscal | « Renvoyer » avec « Justifier AC-002 avant validation » | Statut « à corriger » ; ligne ajoutée à l'historique des validations |
| W14 | comptable | « Soumettre » | Statut « à valider » (depuis « à corriger ») |
| W15 | responsable fiscal | « Valider » | Statut « validée » |
| W16 | responsable fiscal | « Déposer » sans référence | Refus : « La référence du virement est obligatoire pour déposer la déclaration. » |
| W17 | responsable fiscal | « Déposer » avec `VIR-2026-10-0001` | Statut « déposée », date de dépôt et référence affichées |
| W18 | responsable fiscal | « Archiver » | Statut « archivée » ; historique des validations complet (qui, étape, décision, commentaire, date) |
| W19 | admin | « Générer l'IRSA », « Soumettre », puis « Valider » avec le même compte | Le bouton « valider » est grisé : « Séparation des tâches : la personne qui a soumis la déclaration ne peut pas la valider. » (si la génération est refusée, noter le message) |
| W20 | responsable fiscal | Règles, R2 : poids de 2 à 4, « Aperçu » | Score `≈` +24 points (de ≈ 71,5 vers ≈ 95,5) ; recharger la page : le poids est toujours 2 (rien enregistré) |
| W21 | responsable fiscal | Même modification sans motif, « Appliquer » | Refus : motif obligatoire |
| W22 | responsable fiscal | Même modification avec « Risque accru sur les doublons de facture », « Appliquer » | Poids enregistré à 4 |
| W23 | responsable fiscal | Règles, R6 : décocher « active », « Appliquer » avec un motif | Refus : désactiver une règle bloquante est réservé à l'admin |
| W24 | admin | Audit | Lignes des actions faites (connexions, justification, correction, soumission, validation, dépôt, règles) ; chaîne de hash intacte |
| W25 | terminal | `/opt/lampp/bin/php spark audit:verifier` | « Journal intact : N ligne(s) vérifiée(s). » |
| W26 | comptable | Passer à la société propre | Score 0 / 100, niveau faible, bandeau vert, aucune anomalie |

## 5. Cas limites des règles R3, R4 et R5

Ces cas modifient des données en base. Ils sont joués par le script, pas à la main :

    ./tests_r3r4r5.sh 2>&1 | tee resultats_tests.txt
    ./demo_preparer.sh

Attendu : 20 scénarios conformes, puis 85 / 100 et 0 / 100.

## 6. Recette automatique

    ./recette.sh 2>&1 | tee resultats_recette.txt
    ./demo_preparer.sh

Attendu : « 0 KO ».

## 7. Fiche de suivi

Pour chaque étape, noter OK ou KO, ce qui s'est affiché, et le score réel quand c'est demandé.

| Étape | OK / KO | Observé |
|---|---|---|
| B1 à B11 | | |
| K1 à K6 | | |
| W01 à W06 | | |
| W07 (score réel : ____) | | |
| W10 (score réel : ____) | | |
| W11 à W19 | | |
| W20 (score réel avant / après : ____ / ____) | | |
| W21 à W26 | | |
| Défauts d'affichage (alignement, lisibilité, couleurs) | | |
