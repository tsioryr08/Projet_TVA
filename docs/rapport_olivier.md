# Rapport - partie Olivier : workflow, tableau de bord, saisie des données, tests croisés

## 1. Workflow de validation d'une déclaration

Une déclaration (TVA, IRSA ou retenues) est générée en brouillon à partir des écritures et des bulletins de la période, puis suit un circuit à cinq statuts : brouillon, à valider, validée, déposée, archivée, avec un statut « à corriger » pour les renvois. Le schéma est dans `docs/diagramme_workflow.svg`.

| Action | Statut de départ | Statut d'arrivée | Rôle autorisé | Conditions |
|---|---|---|---|---|
| soumettre | brouillon, à corriger | à valider | comptable, admin | aucune anomalie bloquante ouverte |
| renvoyer | à valider | à corriger | responsable fiscal, admin | commentaire obligatoire |
| valider | à valider | validée | responsable fiscal, admin | aucune anomalie bloquante ouverte ; la personne qui a soumis ne peut pas valider |
| déposer | validée | déposée | responsable fiscal, admin | référence de virement obligatoire |
| archiver | déposée | archivée | responsable fiscal, admin | aucune |

Principes retenus :

- **Blocage sur gravité maximale.** Tant qu'une anomalie de gravité « bloquante » est ouverte pour la société et la période (règle active), la déclaration ne peut ni être soumise ni être validée. Une anomalie justifiée, corrigée ou ignorée ne bloque plus. Une anomalie bloquante ne peut être justifiée que par un responsable fiscal ou un admin.
- **Séparation des tâches.** La personne qui a soumis une déclaration ne peut pas la valider.
- **Un seul point de décision.** Les droits et les refus sont décidés par le service de workflow, pas par l'interface. L'écran affiche tous les boutons, grise ceux qui sont indisponibles et indique le motif exact du refus.
- **Traçabilité.** Chaque transition est enregistrée dans une transaction : changement de statut, ligne dans l'historique des validations (utilisateur, étape, décision, commentaire, date) et ligne dans le journal d'audit. Le journal est chaîné par hash : modifier ou supprimer une ligne casse la chaîne, ce que la vérification détecte.

## 2. Tableau de bord de risque

Le tableau de bord est calculé par un service qui renvoie toutes les données d'une société et d'une période (le même contenu est disponible en JSON à l'adresse `/api/dashboard/{société}/{période}`). L'interface n'effectue aucun calcul : elle affiche. Les graphiques sont dessinés avec Chart.js. Un sélecteur en haut de page change la société et la période, ce qui permet de comparer la société « à risque » et la société « propre ».

Contenu de la page, de haut en bas :

1. **Bandeau de validation** : rouge « validation bloquée » avec le nombre d'anomalies bloquantes ouvertes, ou vert si la validation est possible.
2. **Quatre chiffres clés** : anomalies ouvertes sur le total, anomalies bloquantes ouvertes, montant à risque encore ouvert, taux de traitement.
3. **Jauge du score de risque** (0 à 100) colorée selon le niveau (faible, moyen, élevé, critique), avec la variation par rapport à la période précédente.
4. **Évolution du score** sur les 12 dernières périodes (courbe).
5. **« Pourquoi X / 100 ? »** : le score expliqué règle par règle (nombre d'anomalies, poids, points, contribution) avec le facteur historique.
6. **Anomalies par règle** (barres) et **par gravité** (anneau).
7. **Carte de chaleur** : nombre d'anomalies par règle et par période.
8. **Les cinq plus grosses anomalies ouvertes**, avec lien vers leur dossier de preuve.
9. **Échéances des déclarations** : état « terminé », « ok », « alerte » (7 jours ou moins) ou « en retard ».

Les regroupements par statut et par impôt sont calculés par le service et disponibles dans le JSON, mais ne sont pas affichés dans la page actuelle.

Score de risque (paramètres dans `app/Config/Score.php`) : points d'une anomalie = points de sa gravité (info 0, moyenne 1, élevée 2, bloquante 3) x poids de la règle x coefficient de son statut (ouverte 1, justifiée 0,5, corrigée 0, ignorée 0). Score = plafonné à 100 de (multiplicateur x somme des points) plus 10 si la société a déjà subi un redressement sur une règle qui se déclenche aujourd'hui. Niveaux : moins de 25 faible, 25 à 49 moyen, 50 à 74 élevé, 75 et plus critique. [Multiplicateur à confirmer avec Tsiory : voir les notes de travail.]

## 3. Saisie des données (bordereaux et historique des contrôles)

- **Bordereaux** : formulaire avec le PDF joint. Le fichier est vérifié (PDF réel, 5 Mo maximum, doublon refusé par empreinte SHA-256), rangé hors du dossier public, et trois champs lus sur le document sont enregistrés : montant TTC, date du document, NIF de l'émetteur. Le document et ses trois champs sont enregistrés ensemble ou pas du tout, et l'action est inscrite dans le journal d'audit.
- **Historique des contrôles fiscaux** : date, impôt, règle concernée, motif, montant du redressement. Après chaque saisie, le score de la période est recalculé et l'effet est affiché.
- **Choix assumé : pas d'extraction automatique du texte des PDF.** Les champs sont saisis à la main à partir du PDF joint. L'extraction automatique est prévue en évolution : ce n'est pas un oubli.
- Droits : la saisie des bordereaux est réservée au comptable et à l'admin, celle de l'historique au responsable fiscal et à l'admin (il influence le score). Tous les rôles peuvent consulter.

## 4. Test croisé des règles R3, R4 et R5

Méthode : sur la société à risque (octobre 2026), modification d'une donnée en base, nouvelle exécution des règles, comparaison du résultat avec le résultat attendu d'après le code. Base remise dans son état initial avant chaque scénario.

| Règle | Ce qui a été vérifié | Résultat |
|---|---|---|
| R4 | IRSA corrigé, tolérance (écart de 100 accepté, 101 refusé), arrondi de la base à la centaine, personnes à charge, minimum de perception de 3 000 Ar, barème 2026 sur une grosse base | conforme |
| R3 | masse salariale alignée, tolérance (écart de 1 000 accepté, 1 001 refusé), bulletins à zéro | conforme |
| R5 | retenue correcte, absente, tolérance (100 accepté, 101 refusé), tiers résident, charge hors compte 622 | conforme |

Résultat : 20 scénarios joués, 20 conformes au comportement attendu.

Ces tests montrent que les règles font ce que leur code annonce. Ils ne prouvent pas que les taux et le barème sont exacts (voir les limites).

### Recette automatique

Un script se connecte avec les trois rôles, ouvre chaque écran en vérifiant l'absence d'erreur, puis teste la saisie : envoi valide, doublon, faux PDF, montant, date et NIF invalides, droits selon le rôle. Les écrans répondent correctement pour les trois rôles, les saisies invalides sont refusées sans rien enregistrer, et le journal d'audit reste intact après les saisies.

## 5. Limites et évolutions

- **Valeurs légales non vérifiées** : le barème IRSA 2026 (tranche à 25 % au-delà de 4 000 000 Ar), les cotisations CNaPS, OSTIE et FMFP, le salaire minimum et l'échéance du 15 du mois reposent sur des sources secondaires ou sur un texte non lu. Elles sont marquées « non vérifié » en base.
- **Bordereaux** : les bordereaux saisis sont enregistrés et consultables, mais aucune règle ne les lit encore. R2 contrôle le document lié à une écriture, R3 compare les bulletins de paie à la comptabilité.
- **R3** compare la somme des salaires bruts des bulletins au compte 641 ; elle ne vérifie pas que la base imposable vaut le brut moins les cotisations.
- **R5** ne contrôle que les paiements de services à des non-résidents, sur le compte 622 ; les tiers résidents soumis à retenue ne sont pas contrôlés. Si le taux n'est pas trouvé à la date de l'écriture, l'écriture est ignorée sans message.
- **R4** ne renvoie rien si aucun barème n'est en vigueur à la date de la période, sans signaler qu'elle n'a rien contrôlé.
- **Journal d'audit** : il détecte la modification ou la suppression d'une ligne, mais pas la reconstruction complète de la chaîne sans conservation externe du dernier hash.
- **Connexion** : pas de blocage après plusieurs échecs (évolution prévue) ; les échecs sont tracés dans le journal d'audit.
- **Les déclarations générées** reprennent les écritures telles quelles, y compris celles qui portent des anomalies : c'est le rôle du workflow de les bloquer.

---

# Notes de travail (à ne pas copier dans le rapport)

1. **Formule du score.** Le cahier de répartition (PDF) propose gravité de 1 à 3 et un multiplicateur de 5 (12 points = 60, puis +10 = 70). Le code applique un multiplicateur de 3 et quatre gravités (info 0, moyenne 1, élevée 2, bloquante 3). Demander à Tsiory la formule finale et aligner le texte du rapport.
2. **Schéma du LISEZMOI.** Il montre « à corriger » qui retourne à « brouillon ». Le code fait autrement : depuis « à corriger », l'action « soumettre » mène directement à « à valider ». Le diagramme suit le code.
3. **demo:reinitialiser** ne remet pas les données sources (écritures, bulletins, tiers, documents, historique). Après une modification de ces données, recréer la base (migrate puis db:seed).
4. **Anomalie ignorée** : elle compte pour zéro dans le score. L'action est réservée au responsable fiscal et à l'admin, avec commentaire obligatoire et trace dans le journal. À mentionner si le professeur pose la question.
