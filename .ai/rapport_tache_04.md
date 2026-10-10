# Rapport final — Tâche 04 : corrections post-revue de l'auth v2

## 1. Résumé

Les 10 points de correction (C1–C10) sont traités. Les codes d'erreur métier sont exposés en `code` de premier niveau, l'access token reste à 1440 min, `otp-verify` repasse à 5/min, `forgot-password/reset` attend `reset_token`, `PUT /auth/profile` ne gère plus la locale, `GET /locale` renvoie des `null` explicites et `login/resend-code` exige `challenge_token`. L'OpenAPI est complétée et validée par Redocly (0 erreur, 0 avertissement) et confrontée aux réponses réelles. Suite complète verte : 80 tests, 600 assertions.

## 2. Fichiers créés / modifiés / supprimés

Créés
- `tests/Feature/Docs/OpenApiRoutesCoverageTest.php`
- `tests/Feature/Docs/OpenApiSamplesTest.php`
- `tests/Feature/AuthAuditClosureTest.php`
- `tests/Feature/AuthLocalizationTest.php`

Modifiés (principaux)
- `openapi.yaml`
- `app/Exceptions/ApiException.php`, `bootstrap/app.php`
- `app/Services/OtpService.php`, `app/Jobs/SendOtpEmail.php`
- `app/Http/Controllers/Api/V2/Auth/*.php` (Login, ForgotPassword, Register, RefreshToken, Profile, Device)
- `app/Http/Requests/V2/*.php` (ResetPasswordRequest, UpdateProfileRequest, …)
- `app/Providers/AppServiceProvider.php`, `config/auth_api.php`, `.env.example`
- `app/Http/Resources/V2/{UserResource,LocaleResource}.php`, `app/Services/Locale/LocaleContext.php`
- `lang/en/{auth,locale}.php`, `lang/fr/auth.php`
- `tests/Feature/*` (AuthErrorCodes, AuthTokenExpiration, AuthDevice, AuthProfile, Api/V2/LocaleTest, …)
- `.ai/changelog.md`

Supprimés
- Composant `ValidationError` dans `openapi.yaml` (devenu inutilisé après éclatement inline des réponses 422).

## 3. Décisions prises

- **C8#6** : `support@example.com` et l'URL de licence génériques laissés tels quels et signalés comme « à renseigner par l'humain » (aucune valeur inventée).
- **C8#5** : `ar` conservé car présent dans la table `languages` (fr/en/ar) ; pas de retrait.
- **C8** : suppression du composant partagé `ValidationError` plutôt que de le laisser : après avoir documenté chaque 422 inline (C8#2), il n'était plus référencé et déclenchait `no-unused-components`.
- **C9#7** : la route publique `verify-pending-email` reste publique (usage : utilisateur déconnecté). La recherche est restreinte à `pending_email` uniquement.
- **C1** : les noms d'erreur existants sont réutilisés tels quels (`otp_max_attempts`, `invalid_refresh_token`, `invalid_token`), conformément à C1#2.

## 4. Écarts avec le prompt

Aucune déviation de contrat public. Points où la réalité diffère du texte du prompt et où le code a été privilégié (règle §4.2 du contexte) :
- **C2** : l'audit et la config donnaient déjà 1440 min ; la tâche 02 avait introduit 60 min par défaut → retour à 1440 via `access_token_ttl_minutes` (voir changements cassants).
- **C6** : le code renvoyait déjà `country: null` dans les cas multi-pays ; c'est l'OpenAPI qui divergeait (`source: none`). L'OpenAPI a donc été corrigée, plus une correction de description.
- **C3** : le limiteur avait été renommé `otp-verify` ; la valeur a été ramenée à 5/min avec les mêmes clés qu'`auth-otp-verification`.

## 5. Changements cassants

| # | Route / champ | Ancien | Nouveau |
|---|---|---|---|
| C1 | Toutes les réponses 4xx | `{message}` (+ `errors`) | `{code, message}` (+ `errors`) — `code` de premier niveau, stable |
| C2 | Durée access token | 60 min (introduit tâche 02) | 1440 min (défaut d'origine) |
| C4 | `POST /auth/forgot-password/reset` | champ `token` | champ `reset_token` |
| C5 | `PUT /auth/profile` | acceptait `country_id`, `language_id` | champs retirés ; `PATCH /api/v2/locale` seul point d'écriture |
| C6 | `GET /locale` | `country.source` pouvait valoir `none` | `language`/`country` `null` explicites ; `source ∈ {profile, single_active}` |
| C7 | `POST /auth/login/resend-code` | `challenge_token` **ou** `email` (optionnels) | `challenge_token` obligatoire ; repli `email` retiré |
| C8 | `openapi.yaml` | exemple unique `message` sur `AuthTokenResponse` ; pas de `Content-Language`/`Vary` ; codes d'erreur incomplets | exemple par endpoint, en-têtes standard, énumération complète des `code` |

## 6. Résultats des tests

- Commande : `php artisan test --compact --no-ansi`
- Résultat : **80 tests, 600 assertions, 0 échec**.
- Formatage : `vendor/bin/pint --dirty` appliqué.
- Linter OpenAPI : `npx --yes @redocly/cli lint openapi.yaml` → « Woohoo! Your API description is valid » (0 erreur, 0 avertissement).
- Tests dédiés : `AuthErrorCodesTest` (13), `AuthAuditClosureTest` (5), `AuthLocalizationTest` (5), `AuthTokenExpirationTest` (1), `OpenApiRoutesCoverageTest` (1), `OpenApiSamplesTest` (5), `Api/V2/LocaleTest` (C6).

### Tableau de clôture des 15 problèmes de l'audit (C9)

| # | Problème | Statut | Preuve |
|---|---|---|---|
| 1 | Révocation de refresh token non traçable | Corrigé | Migration `2026_10_09_230956_add_revocation_tracking_to_refresh_tokens_table.php` (`revoked_reason`, `revoked_at`, `rotated_at`) ; `AuthAuditClosureTest::reusing a rotated refresh token revokes the whole family` |
| 2 | Identité d'appareil instable | Corrigé | `family_id` (UUID) exposé comme `id` ; `AuthAuditClosureTest::refresh keeps the device family id stable and does not duplicate devices` |
| 3 | Doublon dans `GET /devices` | Corrigé | Même test que #2 |
| 4 | Access token sans expiration explicite | Corrigé | Création avec expiration explicite ; `AuthTokenExpirationTest::the access token expires_in reflects the real token expiration` |
| 5 | Emails OTP synchrones | Corrigé | `SendOtpEmail` + `afterResponse()` ; `AuthAuditClosureTest::otp emails are dispatched only after the HTTP response` |
| 6 | Anti-énumération absent | **Partiel** | Login uniformisé (`AuthLoginTest::it rejects login with invalid credentials`) ; réponses identiques forgot-password côté contrôleur. **Reste** : `RegisterSendCodeRequest` (`unique:users,email`) et `ForgotPasswordSendCodeRequest` (`exists:users,email`) fuient l'existence via 422 de validation |
| 7 | `verify-pending-email` publique potentiellement vulnérable | Corrigé (restreint) | Recherche limitée à `pending_email` ; `AuthAuditClosureTest::verify-pending-email only accepts the pending address` |
| 8 | Pas de `family_id` | Corrigé | Migration (cf. #1) ; test #2 |
| 9 | Pas de détection de réutilisation de refresh token | Corrigé | `AuthAuditClosureTest::reusing a rotated refresh token revokes the whole family` |
| 10 | Révocation de sessions incomplète | Corrigé | Changement de mot de passe et reset révoquent tous les tokens ; `AuthAuditClosureTest::account deletion requires the password and revokes every token` |
| 11 | Messages de validation sans `code` stable | Corrigé | `AuthErrorCodesTest` (une assertion par ligne du catalogue C1) |
| 12 | Pas de `expires_in` dans les réponses de token | Corrigé | `expires_in` / `refresh_expires_in` ; `AuthTokenExpirationTest` |
| 13 | `UserResource` non uniforme sur `/refresh` | Corrigé | `/refresh` renvoie le `UserResource` complet (`OpenApiSamplesTest::login and refresh flow responses…`) |
| 14 | Valeurs de délai en dur | Corrigé | `config/auth_api.php` + `.env.example` |
| 15 | Suppression de compte sans vérif mot de passe | Corrigé | `DeleteAccountRequest` + `AuthAuditClosureTest::account deletion requires the password and revokes every token` (sans mdp → 422, mauvais → `password_current_incorrect`, correct → 200 + révocation access/refresh) |

### C8#9 — Vérification par échantillonnage

`OpenApiSamplesTest` confronte la forme documentée aux réponses réelles (register, login+refresh, locale+countries, devices, erreurs 422/429/401). **Aucun écart détecté.**

### C10 — Écarts de traduction et effet réel de la langue

Clés/fichiers présents dans `lang/en/` et absents de `lang/fr/` :
- Fichiers entiers manquants : `validation.php`, `passwords.php`, `pagination.php`.
- Dans `auth.php` : clé `verification_code_confirmed` présente en `en`, absente en `fr`.
- Tous les autres fichiers (`auth.php`, `locale.php`) ont des clés identiques.

Ce que la langue change réellement :
- **Messages d'API métier** : `lang/{en,fr}/auth.php` (traduits).
- **Messages de validation Laravel** : `lang/en/validation.php` existe, `lang/fr/validation.php` **absent** → les messages de validation restent en anglais même avec `X-Locale-Override: fr`.
- **Emails OTP** : sujet et corps dans `lang/{en,fr}/auth.php` (`mail.*`), traduits selon la locale résolue au moment de l'envoi.
- **Noms de pays / langues** : issus de la base, **non traduits**.

Preuve : `AuthLocalizationTest` (`Content-Language: fr`, messages fr, `code` identique dans les deux langues).

### C9 — Vérifications spécifiques

- #1/#8/#9/#10 : migration de traçabilité confirmée ; test de réutilisation d'un refresh token tourné présent et vert.
- `family_id` stable après refresh et aucun doublon dans `GET /devices` : confirmé par test.
- `password_current_incorrect` sur suppression de compte confirmé.

## 7. Observations hors périmètre

- **Fuites d'existence** (liées à #6, non corrigées car hors périmètre C) : `RegisterSendCodeRequest` (`unique:users,email`) et `ForgotPasswordSendCodeRequest` (`exists:users,email`) renvoient un 422 de validation distinct pour email connu/inconnu.
- **Traductions manquantes** : `lang/fr/` sans `validation.php`, `passwords.php`, `pagination.php` ; `verification_code_confirmed` absente de `lang/fr/auth.php`.
- `verify-pending-email` reste publique : le risque d'interception d'email (déjà noté par l'audit) subsiste par conception.
- Les scripts de développement (Inspecteur/insomnia) et le fichier `notes/auth_v2_summary.md` n'ont pas été touchés.

## 8. Points à confirmer par un humain

- Champs OpenAPI `contact`/`license` : `support@example.com` et URL de licence génériques à renseigner (C8#6).
- Choix de conserver `ar` dans les exemples OpenAPI (présent en base).
- Anti-énumération sur `register/send-code` et `forgot-password/send-code` : à corriger lors d'une tâche dédiée si souhaité (hors périmètre C).
- Ajouter `lang/fr/validation.php`, `passwords.php`, `pagination.php` et la clé `verification_code_confirmed` si le support complet du français est requis.
