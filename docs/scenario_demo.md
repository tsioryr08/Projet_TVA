# Scénario de démonstration (environ 12 minutes) - à jouer à deux

Préparation, 10 minutes avant :
1. `./demo_preparer.sh` : doit afficher 85 / 100 (société à risque) et 0 / 100 (société propre).
2. `.env` : `CI_ENVIRONMENT = production` (pas de barre de debug). Internet branché (Chart.js), sauf si la tâche « Chart.js hors ligne » est faite.
3. Deux navigateurs (ou une fenêtre privée) pour être connecté en même temps en comptable et en responsable fiscal.
4. Mot de passe de tous les comptes : `Demo@2026`. Comptable : comptable@demo.mg. Responsable fiscal : fiscal@demo.mg. Admin : admin@demo.mg.
5. Période 10/2026 sélectionnée en haut de page.

Les valeurs chiffrées ci-dessous viennent de la formule du score (points = gravité x poids x statut, multiplicateur 3, plus 10 d'historique). L'écran fait foi : tout écart est à noter dans la dernière section.

## Étape 1 - Les deux sociétés (comptable)
- Tableau de bord de la société propre : score 0 / 100, niveau faible, bandeau vert « la validation est possible ».
- Passer à la société à risque avec le sélecteur : score 85 / 100, niveau critique, 8 anomalies dont 2 bloquantes, bandeau rouge « validation bloquée ».
- « Pourquoi 85 / 100 ? » : points par règle R1 3, R2 8, R3 1, R4 3, R5 4, R6 6 (total 25, soit 75 avec le multiplicateur 3), plus 10 de facteur historique (redressement passé sur R2).
- Montrer le graphique d'évolution, la carte de chaleur et les échéances.

## Étape 2 - Une anomalie et son dossier de preuve
- Menu Anomalies, ouvrir l'anomalie R5 (écriture AC-004, 100 000 Ar).
- Montrer : le constat (retenue de 100 000 au lieu de 200 000), la base légale, le dossier de preuve (lignes d'écriture sources), les quatre actions.
- Ouvrir aussi un doublon R2 (AC-005) : le dossier de preuve montre les deux écritures.

## Étape 3 - Le blocage (comptable)
- Menu Déclarations, « Générer la TVA » : une déclaration en brouillon apparaît.
- Cliquer « Soumettre » : refus, message « 2 anomalie(s) bloquante(s) ouverte(s) : corrigez-les ou justifiez-les avant de continuer. »
- Cliquer le lien du bandeau rouge du tableau de bord pour voir les 2 anomalies bloquantes (R6 : OD-001 déséquilibrée, OD-002 hors période).

## Étape 4 - Lever le blocage
a) OD-002 (écriture du 30/09), toujours en comptable : ouvrir l'anomalie, « Justifier » avec un commentaire court (« ok ») : refus (10 caractères minimum). Puis avec un commentaire long : refus encore, « une anomalie bloquante ne peut être justifiée que par un responsable fiscal ou un administrateur ». C'est voulu.
b) Passer dans l'autre navigateur, connecté en responsable fiscal : ouvrir OD-002, « Justifier » avec « Écriture du 30/09 reclassée en octobre, validée par la direction ». Le message affiche le score avant et après.
c) OD-001 (débit 300 000, crédit 250 000) : corriger la donnée dans un terminal :
   `/opt/lampp/bin/mysql -u root MABASE -e "UPDATE lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id SET l.credit=300000 WHERE e.societe_id=1 AND e.numero_piece='OD-001' AND l.credit=250000;"`
   Dans l'interface (comptable ou responsable fiscal) : OD-001, bouton « Corriger ». Avant la correction des données, ce bouton est refusé (« toujours détectée sur les données actuelles »).
d) Score attendu : environ 71,5 (R6 passe de 6 à 1,5 point, soit 20,5 points x 3 + 10). Bandeau du tableau de bord : vert.

## Étape 5 - Soumission et validation
- Comptable : Déclarations, ouvrir la TVA, « Soumettre » : la déclaration passe à « à valider ».
- Responsable fiscal : « Valider » : passe à « validée ». L'historique des validations montre qui a fait quoi.
- Variante (30 secondes) pour la séparation des tâches : en admin, soumettre puis valider avec le même compte : refus.
- Responsable fiscal : « Déposer » sans référence : refus. Avec `VIR-2026-10-0001` : « déposée ». Puis « Archiver » : « archivée ».

## Étape 6 - Règles paramétrables (responsable fiscal)
- Menu Règles, R2 : poids de 2 à 4, cliquer « Aperçu » : le score monte d'environ 24 points (2 anomalies x 4 points de plus x 3), rien n'est enregistré.
- Saisir un motif, « Appliquer » : la modification est enregistrée et tracée.

## Étape 7 - Audit (admin)
- Menu Audit : les lignes des actions faites (justification, soumission, validation, dépôt, règles), « chaîne de hash intacte ».
- Terminal : `/opt/lampp/bin/php spark audit:verifier` : journal intact.

## Étape 8 - La saisie (1 minute, notre apport)
- Comptable, menu Bordereaux : saisir un bordereau avec un PDF, ouvrir le PDF.
- Responsable fiscal, menu Contrôles : enregistrer un contrôle, le message affiche le score avant et après.
- Dire : l'extraction automatique des PDF est un choix assumé, prévue en évolution.

## Après chaque répétition
`./demo_preparer.sh` (restaure la base, supprime les PDF de test, remet 85 et 0).

## À noter pendant la répétition
Pour chaque écart, noter : étape, ce qui était attendu, ce qui s'est affiché, capture d'écran.
- Étape 1 : [ ]
- Étape 2 : [ ]
- Étape 3 : [ ]
- Étape 4 : [ ] (score réel après justification et correction : ____)
- Étape 5 : [ ]
- Étape 6 : [ ] (variation réelle du score : ____)
- Étape 7 : [ ]
- Étape 8 : [ ]
- Défauts d'affichage (alignement, lisibilité, couleurs) : [ ]
