# Étape 8 : traitement des anomalies (justifier, corriger, ignorer, rouvrir)

Aucune migration, aucun seeder. Étapes précédentes en place.

## Fichiers (tous NOUVEAUX : rien à remplacer)

    app/Libraries/Anomalies/AnomalieService.php      le service
    app/Libraries/Anomalies/AnomalieException.php    refus (message à afficher)
    app/Controllers/AnomaliesApi.php                 API JSON
    app/Commands/AnomaliesListe.php                  php spark anomalies:liste
    app/Commands/AnomaliesTraiter.php                php spark anomalies:traiter

## Routes à ajouter dans app/Config/Routes.php

    $routes->get('api/anomalies/(:num)/(:num)', 'AnomaliesApi::lister/$1/$2');
    $routes->get('api/anomalies/(:num)', 'AnomaliesApi::detail/$1');
    $routes->get('api/anomalies/(:num)/actions', 'AnomaliesApi::actions/$1');
    $routes->post('api/anomalies/(:num)/traiter', 'AnomaliesApi::traiter/$1');

## En ligne de commande

    php spark anomalies:liste 2026 10 [--societe 2] [--statut ouverte] [--regle R2] [--severite bloquante]
    php spark anomalies:traiter <id> justifier --commentaire "Avoir émis en novembre"
    php spark anomalies:traiter <id> corriger
    php spark anomalies:traiter <id> ignorer --commentaire "..." --par fiscal@demo.mg
    php spark anomalies:traiter <id> rouvrir --commentaire "..." --par fiscal@demo.mg

Les ids changent à chaque demo:reinitialiser : toujours relire la liste avant de traiter. Utilisateur par défaut : comptable@demo.mg.

## Les quatre actions

| Action | Statut de départ | Arrivée | Qui | Conditions |
|---|---|---|---|---|
| justifier | ouverte | justifiee | comptable, responsable_fiscal, admin | commentaire de 10 caractères minimum ; une anomalie BLOQUANTE n'est justifiable que par responsable_fiscal ou admin |
| corriger | ouverte, justifiee | corrigee | comptable, responsable_fiscal, admin | refusée si l'anomalie est toujours détectée sur les données actuelles (le moteur est rejoué, puis tout est annulé) |
| ignorer | ouverte, justifiee | ignoree | responsable_fiscal, admin | commentaire obligatoire |
| rouvrir | justifiee, corrigee, ignoree | ouverte | responsable_fiscal, admin | commentaire obligatoire |

Effet sur le score : ouverte pèse 1, justifiée 0,5, corrigée et ignorée 0. Toute action recalcule et enregistre le score, et
la réponse donne le score avant et après, le nombre d'anomalies bloquantes restantes et si la validation est encore bloquée.
Une règle désactivée n'a plus d'anomalie traitable.

Pourquoi corriger est vérifié : sans cela, il suffirait de cliquer « corrigée » pour lever un blocage. Le service retire l'anomalie
dans une transaction annulée, rejoue le moteur, et refuse si la même règle la détecte encore sur la même écriture ou le même bulletin.

## Service PHP (pour les écrans)

    $s = new \App\Libraries\Anomalies\AnomalieService();
    $s->lister($societeId, $periodeId, ['regle' => 'R2', 'severite' => 'bloquante', 'statut' => 'ouverte']);
    $s->detail($anomalieId);                       // anomalie, règle (source légale), preuves, historique des traitements
    $s->actionsPossibles($anomalieId, $userId);    // boutons à afficher ou griser, avec le motif du refus
    $s->traiter($anomalieId, 'justifier', $userId, 'Avoir émis en novembre');   // AnomalieException si refusé

## API JSON

    GET  /api/anomalies/{societe_id}/{periode_id}?regle=R2&severite=bloquante&statut=ouverte    liste filtrée
    GET  /api/anomalies/{id}                                                                     détail : preuves et historique
    GET  /api/anomalies/{id}/actions?utilisateur_id=2                                            actions possibles
    POST /api/anomalies/{id}/traiter    corps : {"action": "justifier", "commentaire": "...", "utilisateur_id": 2}

Erreurs : 422 avec {"erreur": "..."} pour un refus, 404 si l'anomalie est introuvable.
Comme pour les autres URL JSON, utilisateur_id devra venir de la session une fois l'authentification en place.

## Tests réalisés

Liste et filtres ; justification valide ; refus pour commentaire trop court, pour justification d'une anomalie bloquante par
le comptable, pour « corriger » sans avoir corrigé les données, pour ignorer et rouvrir par le comptable ; correction réelle
des écritures OD-001 et OD-002 puis passage à « corrigée » (le score passe de 85 à 67 et la validation se débloque) ; soumission
de la déclaration refusée avant correction et acceptée après ; ignorer et rouvrir par le responsable ; historique et journal d'audit intact.
