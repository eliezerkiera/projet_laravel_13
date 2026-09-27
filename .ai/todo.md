# Contexte

Je développe une API REST en Laravel (versionnée, actuellement en **V2**), consommée par une
SPA web et une application mobile.
Je veux implémenter un système de détection automatique du pays et de la langue de l'utilisateur
à chaque requête, tout en permettant à l'utilisateur de modifier manuellement ce choix.

Les modèles `Country` et `Language` existent déjà. Explore leur structure (migrations, fillable,
relations) avant de commencer, et adapte-toi à l'existant plutôt que de le recréer.

Explore aussi comment le versioning V2 est organisé dans le projet (namespaces de contrôleurs,
préfixe de routes, structure de dossiers, etc.) et respecte cette convention pour tout nouveau
code que tu ajoutes. Ne crée pas une structure différente du reste du projet.

## Contexte métier

- Le site est disponible **uniquement au Burkina Faso** pour l'instant, mais l'architecture
  doit être pensée multi-pays dès maintenant (aucune valeur codée en dur, tout passe par
  la table `countries`).
- **Deux langues sont actives dès le lancement : français et anglais.** Le français est la
  langue par défaut du Burkina Faso.
- La gestion des devises (`currency_code`) est déjà en place — ne pas y toucher, ne pas
  la dupliquer.
- **Ne pas ajouter de gestion de timezone** — ce n'est pas un besoin actuel, ne pas l'inclure
  dans les migrations ni dans la logique.
- La table `countries` possède déjà un champ `language_id`, qui représente la langue par
  défaut associée à un pays. Ce champ sert de fallback de langue quand le pays est connu
  mais que la langue ne l'est pas explicitement (ex: `Accept-Language` absent ou sans match).

## Préparer l'extension multi-pays future

- Ajouter une colonne `is_active` (booléen) sur `countries`, pour pouvoir activer/désactiver
  un pays sans déploiement de code. Seul le Burkina Faso doit avoir `is_active = true`
  dans le seeder initial ; les autres pays peuvent exister en base avec `is_active = false`
  s'ils sont déjà seedés, ou ne pas exister du tout si aucun autre pays n'est en base
  actuellement (à vérifier dans l'existant).
- Décider (et me proposer, avec justification) du comportement quand la géolocalisation IP
  détecte un visiteur venant d'un pays **non actif** : autoriser quand même l'accès avec un
  pays/langue de fallback, ou renvoyer une information exploitable par le client pour afficher
  un message du type "service non disponible dans votre pays" — sans bloquer techniquement
  la requête (laisser cette décision de blocage au client/frontend, l'API ne doit que fournir
  l'information `country.is_active` de façon fiable).
- Ne jamais supposer qu'un pays n'a qu'une seule langue possible pour un utilisateur :
  `country.language_id` est une valeur par **défaut**, jamais une contrainte. Un utilisateur
  au Burkina Faso doit pouvoir choisir l'anglais, et inversement.

# Règle fondamentale : ne jamais écraser un choix manuel

Si l'utilisateur a déjà modifié manuellement sa langue et/ou son pays (via un endpoint dédié,
voir plus bas), la détection automatique ne doit plus jamais recalculer ni écraser ces valeurs.
Il faut donc distinguer clairement "valeur détectée automatiquement" de "valeur choisie par
l'utilisateur".

Propose la solution technique la plus propre pour ça (par exemple un booléen
`locale_manually_set` sur `users`, ou deux colonnes de traçabilité séparées `country_source`
/ `language_source` avec des valeurs `auto` / `manual`) — explique ton choix.

# Architecture : un middleware unique + un service dédié

- Un seul middleware, `DetectLocale`, orchestration légère. Il ne fait qu'appeler un service
  et laisser passer la requête.
- Toute la logique (résolution auth/manual/auto, géoloc IP, parsing `Accept-Language`,
  fallback pays→langue, fallback final) vit dans un service dédié, ex:
  `App\Services\LocaleDetectionService`, injecté dans le middleware.
- Ne pas splitter en deux middlewares séparés (un pour la langue, un pour le pays) : la langue
  dépend du pays détecté (fallback), donc les deux sont couplés et doivent être résolus
  ensemble dans le même service, dans le bon ordre.
- Le service attache le résultat à la requête (ex: `$request->attributes->set('country', ...)`
  et `app()->setLocale(...)`), consultable par les contrôleurs pour la durée de la requête.

# Cascade de détection

À chaque requête :

1. **Utilisateur authentifié + choix manuel actif** (flag "manual" sur le champ concerné)
   → utiliser tel quel, ne rien recalculer, ne rien écraser.

2. **Utilisateur authentifié + valeurs déjà en base issues d'une détection auto précédente**
   → ne pas re-détecter tant qu'une valeur existe déjà (éviter de changer la langue d'un
   utilisateur qui voyage avec une IP différente sans l'avoir demandé). Re-détection
   automatique uniquement si le champ est vide.

3. **Visiteur non authentifié, ou utilisateur authentifié sans valeur en base** → détection
   à la volée, sans persistance pour le visiteur non authentifié, avec persistance en base
   (flag "auto") pour l'utilisateur authentifié :
   a. **Pays** : géolocalisation par IP (package `stevebauman/location` ou équivalent que tu
      juges meilleur — justifie si tu changes). Robuste aux IP privées en local/dev (fallback
      propre, avec possibilité de forcer une IP de test via `.env`, ex: `TEST_IP=`).
   b. **Langue** :
      - Tenter de matcher le header `Accept-Language` avec une langue existante dans
        `languages` (`fr` ou `en`).
      - Si aucun match ou header absent → utiliser `country.language_id` du pays détecté
        à l'étape (a).
      - Si le pays n'a pas pu être détecté non plus → fallback sur `config('app.locale')`.

4. Un override explicite via header custom `X-Locale-Override` (ex: `fr` ou `en`) doit
   toujours être respecté en priorité sur la détection automatique, y compris pour un
   visiteur non authentifié (utile pour un sélecteur de langue côté client avant connexion,
   stocké côté client en localStorage/cookie/storage mobile et renvoyé à chaque requête).

# Endpoint de modification manuelle (API V2)

Créer un endpoint dans la V2 (adapter le chemin exact à la convention déjà utilisée dans
le projet, ex: `PATCH /api/v2/user/locale`), permettant à l'utilisateur authentifié de définir
explicitement son `country_id` et/ou `language_id`.

- Validation : les IDs fournis doivent exister dans `countries` / `languages`. Si le pays
  fourni a `is_active = false`, décider s'il faut rejeter ou accepter quand même la
  préférence (proposer une position et justifier).
- Au moins un des deux champs doit être fourni.
- Positionner le flag de traçabilité sur "manual" pour le(s) champ(s) modifié(s).
- Retourner la ressource utilisateur mise à jour (via API Resource si le projet en utilise
  déjà pour la V2).
- Proposer (ou justifier l'absence d') un moyen de revenir en mode auto
  (ex: paramètre `reset_to_auto: true`).

# Contraintes techniques

- Jamais de crash si une étape de détection échoue (try/catch ou vérifications défensives) —
  fallback par défaut de l'app en dernier recours.
- Fonctionne pour requêtes authentifiées (vérifier Sanctum/Passport utilisé dans le projet)
  et non authentifiées (pas de persistance dans ce cas, juste résolution pour la durée
  de la requête).
- Vérifier si le middleware doit être enregistré globalement ou par groupe de version,
  selon la structure du kernel HTTP du projet — expliquer le choix retenu.

# Seeders

- Seeder (ou mise à jour du seeder existant) pour `languages` : au moins `fr` (français)
  et `en` (anglais).
- Seeder pour `countries` : au moins le Burkina Faso, avec `language_id` pointant vers `fr`
  et `is_active = true`. Ne pas inventer d'autres pays si l'existant n'en contient pas déjà.

# Ce que je veux en livrable

1. Migration(s) : colonnes sur `users` (`country_id`, `language_id`, traçabilité manual/auto),
   colonne `is_active` sur `countries` (si absente).
2. Seeders `languages` (fr/en) et mise à jour du seeder `countries` (Burkina Faso actif).
3. `LocaleDetectionService` avec toute la logique de cascade.
4. Middleware unique `DetectLocale`, enregistré au bon endroit (justifier le choix).
5. Endpoint V2 (contrôleur + form request + resource si applicable) pour la modification
   manuelle du pays/langue.
6. Tests (Pest ou PHPUnit selon le projet) couvrant au minimum :
   - préférences manuelles → jamais écrasées par la détection automatique
   - préférences auto déjà en base → pas de re-détection tant que la valeur existe
   - détection pays par IP + fallback langue via `country.language_id`
   - `Accept-Language` prioritaire sur le fallback pays quand disponible
   - `X-Locale-Override` prioritaire sur toute détection, y compris non authentifié
   - endpoint V2 : validation, flag "manual", non-écrasement ultérieur
   - fallback complet (aucun signal) → valeurs par défaut de l'app
   - pays détecté avec `is_active = false` → comportement conforme à ce qui est décidé
7. Résumé final des fichiers créés/modifiés avec les commandes à lancer (migrations,
   composer install si besoin).

# Avant de coder

Explore la structure du projet (modèles `Country`/`Language`, structure de `User`, middlewares
existants, organisation exacte du versioning V2, package de géoloc déjà présent ou non,
système d'auth utilisé, seeders existants) et pose-moi des questions si un point te semble
ambigu plutôt que de supposer.