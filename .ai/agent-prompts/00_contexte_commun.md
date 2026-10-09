# 00 — Contexte commun et règles de travail

> **À lire intégralement avant toute tâche.** Ce fichier est référencé par les prompts 01, 02 et 03.
> Tu es un agent de codage. Tu travailles directement dans le dépôt du projet.

---

## 1. Le projet

- API REST **Laravel** (détecte la version exacte dans `composer.json`, ainsi que la version de PHP), servie sous `/api/v2`.
- Authentification par **Laravel Sanctum** (header `Authorization: Bearer`) + **refresh tokens maison avec rotation**.
- Flux par **code OTP envoyé par email** : inscription, 2FA à la connexion, mot de passe oublié, changement d'email.
  En local : `MAIL_MAILER=log` (codes dans `storage/logs/laravel.log`) et codes aussi visibles dans la table `verification_codes`.
- Tables connues (à confirmer en lisant les migrations) : `users`, `countries` (colonne `is_active`, relation vers une langue), `languages` (colonne `code`), `verification_codes`.
- **Consommateurs de l'API : des développeurs front web et mobile.**
  Objectif général : un contrat **simple, explicite et prévisible**. Le front ne doit **jamais avoir à deviner** une donnée que le back connaît (langue appliquée, pays, durée de vie des tokens, nature d'une erreur).
- **Rien n'est en production.** Les changements cassants sont autorisés (renommer, supprimer, déplacer des routes ou des champs), mais **chaque changement cassant doit être listé** (voir §5).

---

## 2. Faits déjà établis par lecture de code (à re-vérifier avant de t'y fier)

Les fichiers suivants ont été lus. Les chemins exacts sont à confirmer.

### 2.1 `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(DetectLocaleMiddleware::class);
})
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
})
```

`append()` ajoute le middleware à la pile **globale**, exécutée **avant** les middlewares de route comme `auth:sanctum`.

### 2.2 `App\Http\Middleware\DetectLocaleMiddleware`

Appelle `LocaleDetectionService::detect($request)` puis `applyLocale($detection)`, pose des attributs sur la requête (`detected_country`, `detected_language`, `country_source`, `language_source`). Un `catch (\Exception)` **silencieux** retombe sur `config('app.locale')`. Aucun header n'est ajouté à la réponse.

### 2.3 `App\Services\LocaleDetectionService` (comportement actuel)

- `detect()` lit `$request->user()` (guard par défaut, pas `sanctum`).
- Pays : `user->country_id` sinon le pays actif **s'il y en a exactement un**, sinon `null`. Aucune détection par IP.
- Langue, dans cet ordre : `X-Locale-Override` (recherche par `code`, **sans repli** si inconnu) → `user->language_id` → `Accept-Language` via `$request->getPreferredLanguage(['fr','en'])` → langue du pays.
  Piège : `getPreferredLanguage()` de Symfony renvoie le **premier élément de la liste** (`'fr'`) quand aucune langue du header ne correspond. `Accept-Language: de` donne donc `fr` silencieusement.
- **Auto-persistance** : si l'utilisateur n'a pas de `country_id` / `language_id` et qu'une valeur est résolue, `detect()` l'enregistre en base avec `*_source = 'auto'` (la langue seulement si pas d'override).
- Les langues supportées `['fr','en']` sont **codées en dur**.

### 2.4 `App\Http\Controllers\Api\V2\User\LocaleController::update`

- `reset_to_auto` : met **uniquement** `country_source` et `language_source` à `'auto'`. `country_id` et `language_id` **ne sont pas** remis à `null`.
- `country_id` / `language_id` : testés avec `filled()`, donc impossible de remettre un champ à `null`. Enregistrés avec `*_source = 'manual'`.
- Un pays **inactif** est accepté, avec un champ `warning` dans la réponse.
- Réponse : `message` + `user` (`UserResource`).

### 2.5 Hypothèse H1 (très probable, à prouver par un test)

Comme le middleware est global, `$request->user()` vaut `null` pour les requêtes API avec Bearer. Toute la logique « utilisateur connecté » du service (profil, auto-persistance) est donc **morte** aujourd'hui, et le profil n'influence jamais la langue appliquée.

### 2.6 Non lus à ce jour

`UpdateLocaleRequest`, `UserResource`, tous les contrôleurs d'authentification, les `FormRequest` associés, les modèles, les migrations, `routes/api.php`, `config/sanctum.php`, `config/cors.php`, `AppServiceProvider`. **Tu dois les lire.**

---

## 3. Contrats cibles (décisions arrêtées, ne pas les remettre en cause sans demander)

### 3.1 Langue et pays
- Priorité de la langue : `X-Locale-Override` > profil utilisateur > `Accept-Language` > langue du pays > `config('app.locale')`.
- Une valeur inconnue ou non supportée à une étape est **ignorée** (passage à l'étape suivante), jamais d'erreur ni de repli caché.
- **Aucune écriture en base** pendant la détection. Seul `PATCH /api/v2/locale` modifie le profil.
- Un endpoint public `GET /api/v2/locale` dit **explicitement** au front quelle langue et quel pays le back applique, et pourquoi (`source`).
- Chaque réponse API porte `Content-Language`.
- Détail complet dans `.ai/agent-prompts/01_tache_locale_pays.md`.

### 3.2 Erreurs
- Toute erreur métier 4xx renvoie du JSON avec un champ stable `code` (snake_case) en plus de `message`. Le front se base sur `code`, jamais sur le texte de `message`.
- Détail dans `.ai/agent-prompts/02_tache_auth_securite.md`, section M.

### 3.3 Tokens
- Toute réponse qui émet un token donne aussi sa durée de vie en secondes (`expires_in`, `refresh_expires_in`).
- Détail dans `.ai/agent-prompts/02_tache_auth_securite.md`, section C.

---

## 4. Règles d'exécution (valables pour toutes les tâches)

1. **Explorer avant de modifier.** Lis les fichiers concernés, `routes/api.php`, les migrations, les tests existants. Lance `php artisan route:list --path=api/v2` pour voir les routes réelles.
2. **Ne fais confiance ni à ce fichier ni à la documentation existante** : vérifie dans le code. Si la réalité diffère de ce qui est décrit ici, **suis la réalité**, adapte l'implémentation à l'intention décrite, et signale l'écart dans ton rapport.
3. **Aucune migration existante ne doit être modifiée.** Toute évolution de schéma passe par une **nouvelle** migration avec `up()` et `down()` fonctionnels.
4. **Respecte le style du projet** : conventions de nommage, structure (FormRequest, Resource, Service, Policy), `declare(strict_types=1)` si le projet l'utilise. Lance l'outil de formatage du projet (`vendor/bin/pint` s'il existe).
5. **Tests obligatoires** : détecte le framework (Pest ou PHPUnit) et suis les conventions existantes. Chaque comportement spécifié ci-dessous doit avoir au moins un test Feature. Quand c'est pertinent, écris d'abord le test, constate qu'il échoue (rouge), puis corrige (vert).
   Lance **toute** la suite de tests avant de terminer. Aucune régression tolérée. Si un test existant échoue à cause d'un changement cassant voulu, mets-le à jour et mentionne-le dans le rapport.
6. **Pas de nouvelle dépendance Composer** sans l'avoir demandé.
7. **Périmètre** : ne modifie que ce que la tâche demande. Si tu trouves un problème hors périmètre, **ne le corrige pas** : note-le dans la section « Observations hors périmètre » du rapport.
8. **Ambiguïté** : si une ambiguïté change le **contrat public** de l'API (nom d'un champ, code HTTP, route), arrête-toi et pose la question. Pour tout le reste, décide, documente ta décision dans le rapport et continue.
9. **Git** : si tu peux utiliser git, fais des commits atomiques (un par étape logique), messages clairs et préfixés (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`). Ne pousse rien, ne touche pas à la branche principale : travaille sur une branche dédiée.
10. **Sécurité** : n'écris jamais de secret en dur, ne logge jamais un mot de passe, un token complet ou un code OTP en clair (hors environnement local déjà prévu pour les OTP).
11. **Langue** : le code, les noms, les messages d'API et les commentaires de code sont en **anglais**. Les documents Markdown et les rapports sont en **français**.

---

## 5. Format du rapport final (obligatoire à la fin de chaque tâche)

Termine par un rapport structuré avec exactement ces sections :

1. **Résumé** : 5 lignes maximum.
2. **Fichiers créés / modifiés / supprimés** (liste).
3. **Décisions prises** : chaque fois que tu as dû choisir, avec la raison.
4. **Écarts avec le prompt** : ce qui n'a pas été fait ou a été fait différemment, et pourquoi.
5. **Changements cassants** : liste précise (route, champ, comportement), ancien → nouveau.
6. **Résultats des tests** : commande lancée, nombre de tests, résultat.
7. **Observations hors périmètre** : problèmes détectés mais non traités.
8. **Points à confirmer par un humain**.
