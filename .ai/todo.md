# Contexte

On retire complètement la géolocalisation par IP du système de détection de langue/pays.
Le pays n'est plus détecté automatiquement par IP : il doit être **choisi par l'utilisateur**
via l'endpoint de modification manuelle déjà en place, sauf dans un cas particulier détaillé
ci-dessous.

Cependant, l'architecture doit rester pensée pour évoluer vers plusieurs pays actifs dans
le futur (voir section "Règle d'évolutivité" plus bas) — ne fais aucun choix qui suppose
un seul pays de façon définitive.

# Ce qu'il faut retirer

1. Toute référence à `stevebauman/location` (déjà non installé suite au conflit Guzzle,
   mais vérifie qu'aucun code résiduel ne l'importe encore).
2. Si une classe `IpGeolocationService` (ou équivalent) a été créée dans une itération
   précédente, la supprimer, ainsi que ses tests associés.
3. Toute la logique de géolocalisation IP dans `LocaleDetectionService` (résolution du pays
   via IP), y compris le mécanisme `TEST_IP` s'il n'est plus utilisé ailleurs.
4. Les tests qui couvraient spécifiquement la géolocalisation IP (mock HTTP, IP privée, etc.).

# Nouvelle logique de résolution du pays

1. **Si l'utilisateur est authentifié et a déjà un `country_id` en base** (peu importe
   manual ou auto) → utiliser cette valeur telle quelle, ne rien recalculer.

2. **Si l'utilisateur est authentifié sans `country_id` en base, ou si c'est un visiteur
   non authentifié** :
   - Compter le nombre de pays actifs (`is_active = true`) dans la table `countries`.
   - **S'il n'y en a qu'un seul** (cas actuel avec le Burkina Faso) → l'assigner
     automatiquement, avec le flag de traçabilité "auto" pour un utilisateur authentifié
     (pas de persistance pour un visiteur non authentifié, juste résolu pour la requête).
   - **S'il y en a plusieurs** (cas futur, plusieurs pays actifs) → ne rien assigner
     automatiquement. Le pays reste `null` tant que l'utilisateur ne l'a pas choisi
     explicitement via l'endpoint de modification manuelle. Prévoir que l'API puisse
     signaler cette situation au client (ex: un champ `country_selection_required: true`
     dans la réponse ou une route dédiée `GET /api/v2/countries/active` que le client peut
     appeler pour proposer un sélecteur de pays) — propose la solution que tu juges la plus
     propre pour ce point, et explique ton choix.

# Résolution de la langue (inchangée)

La logique de langue reste telle qu'implémentée précédemment, uniquement basée sur :
1. Choix manuel déjà en base → respecté tel quel.
2. Header `Accept-Language` matché avec la table `languages` (`fr`/`en`).
3. Si aucun match, fallback sur `country.language_id` **si un pays a pu être résolu**
   (voir logique ci-dessus). Si aucun pays n'a pu être résolu (cas multi-pays sans choix
   utilisateur), fallback direct sur `config('app.locale')`.
4. `X-Locale-Override` reste prioritaire sur tout le reste, y compris non authentifié.

# Règle d'évolutivité (important)

Le jour où un deuxième pays sera activé (`is_active = true` sur une seconde ligne de
`countries`), le comportement d'auto-assignation du point 2 doit s'arrêter **automatiquement**,
sans modification de code — uniquement parce que le nombre de pays actifs en base sera
passé de 1 à plusieurs. Vérifie bien que ta logique de comptage (`Country::where('is_active',
true)->count()`) est faite de façon à ce que ce changement de comportement soit purement
piloté par la donnée en base, jamais par une constante ou un `.env`.

# Contraintes techniques inchangées

- Ne jamais planter la requête si une étape échoue (try/catch, vérifications défensives).
- Fonctionne pour requêtes authentifiées et non authentifiées.
- Respecter la structure V2 et les conventions déjà en place dans le projet.

# Tests à mettre à jour

- Supprimer les tests liés à la géolocalisation IP.
- Ajouter/adapter :
  - un seul pays actif en base → auto-assigné à un utilisateur authentifié sans `country_id`
  - un seul pays actif en base → auto-résolu (sans persistance) pour un visiteur non
    authentifié
  - plusieurs pays actifs en base → aucun pays auto-assigné, comportement conforme à ce
    qui est décidé pour signaler le besoin de choix au client
  - utilisateur avec `country_id` déjà en base (peu importe manual/auto) → jamais recalculé
  - fallback langue via `country.language_id` toujours fonctionnel quand un pays est résolu
  - fallback direct sur `config('app.locale')` quand aucun pays n'est résolu

# Avant de coder

Relis l'implémentation actuelle de `LocaleDetectionService`, du middleware `DetectLocale`
et de l'endpoint de modification manuelle avant de faire les changements, pour identifier
précisément ce qui doit être retiré et ce qui doit être adapté.


# Ajout au prompt précédent : documentation de test API

En complément de tout ce qui précède, je veux à la fin du travail un fichier Markdown
dédié à la façon de tester manuellement les routes concernées avec un client HTTP comme
**Insomnia** (ou Postman, la logique est la même).

## Emplacement et nom

Crée ce fichier à la racine du projet ou dans un dossier `docs/` s'il existe déjà dans
le projet (vérifie la convention existante) — nomme-le par exemple `docs/testing-locale-api.md`.

## Contenu attendu

Le fichier doit expliquer, de façon claire et actionnable pour quelqu'un qui découvre
le système :

1. **Contexte rapide** : à quoi sert le système (détection langue/pays), et rappel des
   deux headers clés à connaître pour les tests :
   - `Accept-Language` (standard HTTP, utilisé pour la détection automatique de la langue)
   - `X-Locale-Override` (header custom du projet, permet de forcer une langue/pays)

2. **Comment tester chaque scénario de la cascade**, avec pour chacun :
   - la requête à envoyer (méthode, URL, headers nécessaires, body si applicable)
   - le comportement attendu en retour
   - Scénarios à couvrir au minimum :
     a. Visiteur non authentifié, premier appel, sans aucun header particulier
        → comportement attendu selon le nombre de pays actifs en base.
     b. Visiteur non authentifié avec `Accept-Language: en` ou `Accept-Language: fr`
        → langue attendue en retour.
     c. Visiteur non authentifié avec `X-Locale-Override` renseigné → override respecté.
     d. Utilisateur authentifié sans préférence en base → assignation auto (si un seul
        pays actif) ou pas d'assignation (si plusieurs pays actifs) — comment simuler
        chaque cas (ex: quel compte de test utiliser, ou comment faire varier
        `is_active` en base pour le test).
     e. Utilisateur authentifié avec préférence manuelle déjà définie → vérifier que rien
        n'est écrasé même en changeant les headers.
     f. Appel à l'endpoint de modification manuelle (`PATCH /api/v2/user/locale` ou le
        chemin réel retenu) : exemple de body JSON, réponse attendue, et comment vérifier
        ensuite que la préférence est bien "verrouillée" (rejouer une requête avec un
        `Accept-Language` différent et constater que rien ne change).
     g. Si la route de liste des pays actifs a été créée (`GET /api/v2/countries/active`
        ou équivalent) : comment l'appeler et à quoi doit ressembler la réponse.

3. **Comment configurer l'authentification dans Insomnia** pour les scénarios qui
   nécessitent un utilisateur connecté (rappelle quel mécanisme d'auth est utilisé dans
   le projet — Sanctum/Passport — et comment obtenir un token de test : quel endpoint
   de login appeler, comment l'ajouter ensuite dans les headers des requêtes suivantes,
   ex: `Authorization: Bearer {token}`).

4. **Un exemple concret de collection Insomnia** si possible : soit un export JSON minimal
   que l'utilisateur peut importer directement dans Insomnia, soit à défaut une description
   pas-à-pas de comment créer manuellement les requêtes (nom de la requête, méthode, URL,
   headers, body) pour chacun des scénarios listés en point 2.

5. **Note sur les données de test nécessaires** : quels comptes utilisateurs, quelles
   lignes en base (`countries`, `languages`) doivent exister pour rejouer ces scénarios,
   et comment les obtenir rapidement (seeder dédié aux tests, factory, ou commande artisan
   à lancer).

## Ton et niveau attendu

Écris ce fichier pour quelqu'un qui connaît Laravel mais découvre ce système précis pour
la première fois (par exemple un autre développeur qui rejoint le projet, ou un testeur
non-développeur qui a besoin de suivre des étapes précises). Reste concret, avec des
exemples copiables/collables plutôt que des explications abstraites.