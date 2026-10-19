## [2026-10-19] — Seeders languages / currencies / countries via factories
- Remplacement du seed manuel dans `DatabaseSeeder` par des seeders dédiés qui s’appuient sur les factory states.
- Ajout des états `usd()` et `ivoryCoast()` ; alignement de `france()` (inactif) et des fallbacks XOF/EUR sur les états nommés.
- `php artisan migrate:fresh --seed` remplit XOF/EUR/USD, fr/en, BF/SN/CI/FR comme avant.
Fichiers : `database/seeders/{Database,Currency,Language,Country}Seeder.php`, `database/factories/{Currency,Language,Country}Factory.php`, `tests/Feature/DatabaseSeederTest.php`

## [2026-10-19] — Stack Docker locale (PHP, MySQL, Vite)
- Ajout d'un `Dockerfile` PHP 8.5 CLI avec extensions Laravel (pdo_mysql, mbstring, bcmath, zip, intl, gd, pcntl, opcache) et Composer 2.
- Ajout de `docker-compose.yaml` : services `app` (artisan serve :8000), `mysql` 8.4 (DB `projet_laravel_13`, root sans mot de passe comme `.env.example`), et `node` (profil `frontend`, Vite :5173).
- Ajout de `.dockerignore` pour accélérer les builds.
- Pour Docker : définir `DB_HOST=mysql` dans `.env` (déjà forcé côté service `app`).
Fichiers : `Dockerfile`, `docker-compose.yaml`, `.dockerignore`, `.ai/changelog.md`

## [2026-10-10] — Finalisation de la spécification OpenAPI v2 (Tâche 03 - Étape 0)
- Ajout des exemples manquants pour les codes d'erreur `otp_expired`, `otp_max_attempts` et `refresh_token_expired` dans `openapi.yaml`.
- Conversion des exemples d'erreur 422 en tableaux d'exemples nommés (otp_invalid, otp_expired, otp_max_attempts, invalid_refresh_token, refresh_token_expired).
- Validation OpenAPI avec Redocly CLI : 0 erreur, 0 avertissement.
- Tests OpenApiSamplesTest et ApiGuideCoversAllRoutesTest verts (105 tests, 829 assertions).
- Le guide `docs/api/guide_api_v2.md` existe déjà et respecte la structure imposée avec tous les flux documentés.
- L'ancien guide est déjà archivé dans `docs/api/archive/test_auth_v2_final.md`.
Fichiers : `openapi.yaml`, `.ai/changelog.md`

## [2026-10-10] — Alignement du guide API v2 sur les corrections résiduelles
- Mise à jour de `docs/api/guide_api_v2.md` après la tâche 05 : réponses nominales d'inscription/récupération, traductions présentes, chaînage des tokens et cinq cas de résolution du pays.
- Référence OpenAPI liée à son emplacement réel (`openapi.yaml` à la racine) ; ajout des tags OpenAPI à la table des 25 endpoints et correction des limiteurs de `profile/verify-email`.
- Le cooldown OTP est signalé comme une exception possible à l'anti-énumération pour les demandes de renvoi. La collection Insomnia et `notes/auth_v2_summary.md` restent inchangées.
Fichiers : `docs/api/guide_api_v2.md`, `.ai/changelog.md`

## [2026-10-10] — Tâche 05 : corrections résiduelles (anti-énumération, OpenAPI, otp-verify, retrait de l'arabe)
- R1 (#6 anti-énumération) : retrait de `unique:users,email` (`RegisterSendCodeRequest`) et `exists:users,email` (`ForgotPasswordSendCodeRequest`) ; `register/*` et `forgot-password/*` renvoient désormais un `200` au corps identique que le compte existe ou non (aucun OTP pour un compte existant, notification « compte existant » différée via `afterResponse()` ; rien pour un email inconnu) ; `register/complete` renvoie `422 invalid_token` si l'email devient pris entre-temps (pré-vérification + `catch UniqueConstraintViolationException`) ; le `403` de `login` n'est renvoyé qu'après validation du mot de passe.
- R2 : `DELETE /auth/devices/{id}` documente (composant `DeviceNotFoundError`) et renvoie `404 device_not_found` pour un appareil inconnu ou appartenant à un autre utilisateur ; correction d'un bug `current_device_revoked` (calculé avant la révocation, désormais réellement renvoyé) ; composant OpenAPI `NotFoundError` devenu inutilisé supprimé.
- R3 : `POST /auth/profile/verify-email` passe sous le limiteur `otp-verify` (5/min, clé identifiant utilisateur + IP) avec test de dépassement `429`.
- R4 : exemples OpenAPI réels et complets (user complet des réponses de token ; `GET /locale` en 4 scénarios nommés ; `PATCH /locale` ; `GET`/`DELETE /auth/devices` ; `DELETE /auth/devices/{id}` ; `PUT /auth/password`) ; `LocaleContext.required` complété avec `language`/`country` ; `OpenApiSamplesTest` étendu.
- R5 : `lang/fr/{validation,passwords,pagination}.php` créés et `verification_code_confirmed` ajoutée ; langue `ar` retirée (migration de données `2026_10_10_152202_deactivate_arabic_language.php`, seeders, factory, OpenAPI) ; `X-Locale-Override: ar` ignoré sans erreur ; test de parité des clés `fr`/`en`.
Fichiers : `openapi.yaml`, `app/Http/Controllers/Api/V2/Auth/{Register,Device,ForgotPassword,Login}Controller.php`, `app/Http/Requests/V2/{RegisterSendCodeRequest,ForgotPasswordSendCodeRequest}.php`, `app/Jobs/SendExistingAccountNotice.php`, `app/Notifications/ExistingAccountNotification.php`, `app/Providers/AppServiceProvider.php`, `routes/api.php`, `config/app.php`, `lang/fr/*`, `database/migrations/2026_10_10_152202_deactivate_arabic_language.php`, `database/seeders/DatabaseSeeder.php`, `database/factories/LanguageFactory.php`, `tests/Feature/*`, `.ai/changelog.md`

## [2026-10-10] — Mise à jour du guide d'intégration API v2 (Tâche 03)
- Alignement de `docs/api/guide_api_v2.md` sur l'API v2 réelle après les corrections C1–C10.
- Codes d'erreur : catalogue réduit aux **14 codes réels** (`invalid_token` unifié, ajout de `pending_email_verification_required`, `password_current_incorrect`) ; `invalid_token`/`invalid_refresh_token`/`refresh_token_expired` documentés avec leurs messages exacts.
- `login` : `403 pending_email_verification_required` (corps `email`/`pending_email`) et `422 credentials_invalid` ; `login/verify-code` scindé en `invalid_token` / `otp_invalid` / `otp_expired` / `otp_max_attempts`.
- `forgot-password/reset` : champ `reset_token` (corps, table, diagramme, table de référence) ; `register/complete` : `invalid_token`.
- `login/resend-code` : `challenge_token` obligatoire (repli `email` retiré).
- `expires_in` access token corrigé à `86400` (en-têtes des flux login/refresh et note de rafraîchissement proactif).
- `otp-verify` : limite corrigée à **5/min** (section limites et table de référence).
- `PUT /auth/profile` : `country_id`/`language_id` retirés (renvoi vers `PATCH /api/v2/locale`).
- `DELETE /account` : `422 password_current_incorrect`.
- Chapitre 11 : ajout des changements cassants C1–C10 ; checklist §10.5 et parcours §10.4 corrigés (plus de prétendue anti-énumération sur `register/send-code` et `forgot-password/send-code`).
Fichiers : `docs/api/guide_api_v2.md`, `.ai/changelog.md`

## [2026-10-10] — Corrections post-revue de l'auth v2 (C6, C8, C9, C10)
- C6 : `GET /api/v2/locale` renvoie `language`/`country` à `null` quand rien n'est résolu (plus de `source: none`) ; `country_selection_required` documenté comme « au moins un pays actif et aucun pays actif résolu » ; OpenAPI alignée (`LocaleContext`/`User` nullable, `CountryDetail.source ∈ {profile, single_active}`).
- C8 : `openapi.yaml` complétée et validée par Redocly (0 erreur, 0 avertissement). `components.headers` `Content-Language`/`Vary` référencés sur toutes les réponses (dont erreurs) ; énumération complète des `code` (14 valeurs) ; erreurs 4xx réelles documentées par endpoint avec exemples (dont le `403` de `login`) ; règles de `PATCH /locale` et ses 422 ; `AuthTokenResponse.message` remplacé par un exemple par endpoint (inscription/connexion/refresh) ; langues d'exemple limitées à fr/en ; valeurs figées `expires_in`/`refresh_expires_in` retirées de l'introduction. Composant `ValidationError` supprimé car devenu inutilisé après éclatement inline des 422.
- C8 (tests) : `OpenApiRoutesCoverageTest` (YAML vs `Route::getRoutes()` pour `api/v2`) et `OpenApiSamplesTest` (échantillonnage réel register/login+refresh/locale+countries/devices/422/429/401) verts.
- C9 : `AuthAuditClosureTest` couvre la clôture de l'audit — recherche `verify-pending-email` limitée à `pending_email`, envoi des OTP différé (`afterResponse()`), suppression de compte (mot de passe requis + révocation access/refresh), réutilisation d'un refresh token tourné → révocation de la famille, `family_id` stable après refresh et sans doublon dans `GET /devices`. `AuthDeviceTest` réécrit (5/5).
- C10 : `AuthLocalizationTest` vérifie que `X-Locale-Override: fr` renvoie les messages métier en français avec `Content-Language: fr` et `code` inchangé.
Fichiers : `openapi.yaml`, `tests/Feature/Docs/{OpenApiRoutesCoverageTest,OpenApiSamplesTest}.php`, `tests/Feature/AuthAuditClosureTest.php`, `tests/Feature/AuthLocalizationTest.php`, `.ai/changelog.md`

## [2026-10-10] — Corrections post-revue de l'auth v2 (C1–C5, C7)
- C1 : exposition des codes d'erreur métier stables en `code` de premier niveau via `ApiException` (errors par champ) et handler `ValidationException` (`validation_failed` / `password_current_incorrect`) ; correction d'un bug latent `$this->status` jamais initialisé dans `ApiException`.
- C2 : access token TTL par défaut maintenu à 1440 minutes (config, `.env.example`, fallbacks contrôleurs).
- C3 : limiteur `otp-verify` ramené à 5/min (config, `.env.example`, `AppServiceProvider`, OpenAPI à suivre).
- C4 : `forgot-password/reset` attend désormais `reset_token` (changement cassant).
- C5 : `PUT /auth/profile` n'accepte plus `country_id` / `language_id` (changement cassant) ; `PATCH /locale` seul point d'écriture.
- C7 : `login/resend-code` exige `challenge_token` (plus de repli par email).
- C9 (partiel) : OTP envoyés en différé via `SendOtpEmail` (`afterResponse()`), recherche `verify-pending-email` limitée à `pending_email`, `AuthDeviceTest` réécrit sur le modèle refresh-token/family_id.
- Tests : `AuthErrorCodesTest` (une assertion par ligne du catalogue C1), `AuthTokenExpirationTest` ; suite complète verte (63 tests, 401 assertions).
Fichiers : `app/Exceptions/ApiException.php`, `bootstrap/app.php`, `app/Services/OtpService.php`, `app/Jobs/SendOtpEmail.php`, `config/auth_api.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Api/V2/Auth/*.php`, `app/Http/Requests/V2/*.php`, `tests/Feature/{AuthErrorCodesTest,AuthTokenExpirationTest,AuthDeviceTest,AuthForgotPasswordTest}.php`

## [2026-10-10] — Génération et validation de la spécification OpenAPI v2 (openapi.yaml)
- Génération complète du document `openapi.yaml` (spécification OpenAPI 3.0.3) couvrant l'ensemble des 25 endpoints de l'API v2.
- Structuration par tags : `Authentication`, `Profile`, `Devices`, `Locale`, `Countries`.
- Schémas réutilisables complets : `User`, `Device`, `Country`, `Language`, `LocaleContext`, `AuthTokenResponse`, `ValidationErrorResponse`, `RateLimitErrorResponse`, `ErrorResponse`.
- Validation stricte avec Redocly CLI (`@redocly/cli lint openapi.yaml`) : 0 erreur, 0 avertissement.
Fichiers : `openapi.yaml`, `.ai/changelog.md`

## [2026-10-09] — Réécriture du guide de l'API v2 et validation automatisée (Tâche 03)
- Création du guide complet d'intégration et de recette manuelle pour les développeurs front/mobile dans `docs/api/guide_api_v2.md`.
- Couverture exhaustive des 25 endpoints sous `/api/v2` avec exécution réelle, payloads exacts et format standardisé.
- Documentation autonome du système de locale et pays (règle d'or, priorité à 5 niveaux, PATCH partiel et total, exemple TypeScript).
- Spécification des flux d'authentification par diagrammes de séquence Mermaid (inscription, connexion 2FA, mot de passe oublié complet, refresh).
- Explication des règles de gestion des tokens côté client avec exemple d'intercepteur TypeScript single-flight contre les pièges de rotation.
- Archivage de l'ancien document `notes/test_auth_v2_final.md` dans `docs/api/archive/test_auth_v2_final.md`.
- Ajout du test Feature automatisé `tests/Feature/Docs/ApiGuideCoversAllRoutesTest.php` validant la cohérence de la table de référence avec les routes réelles.
- Correction ciblée de la gestion de `Retry-After` sur `ThrottleRequestsException` dans `bootstrap/app.php` et du hash dummy dans `LoginController.php`.
Fichiers : `docs/api/guide_api_v2.md`, `docs/api/archive/test_auth_v2_final.md`, `tests/Feature/Docs/ApiGuideCoversAllRoutesTest.php`, `bootstrap/app.php`, `app/Http/Controllers/Api/V2/Auth/LoginController.php`, `.ai/changelog.md`

## [2026-10-09] — Cohérence, sécurité et prévisibilité de l'authentification (Tâche 02)
- Configuration centralisée des durées et limites via `config/auth_api.php` avec variables d'environnement.
- Ajout de `expires_in` et `refresh_expires_in` (en secondes) à toutes les réponses de token (register, login, refresh).
- Access tokens créés avec expiration explicite (60 min par défaut) au lieu de dépendre uniquement de la config Sanctum.
- Refresh tokens : ajout de colonnes `family_id`, `revoked_reason`, `revoked_at`, `rotated_at` pour traçabilité.
- Implémentation de la rotation atomique des refresh tokens avec lock pour éviter les courses.
- Détection de réutilisation de refresh token avec révocation automatique de toute la famille.
- Stabilisation de l'identité d'appareil : utilisation de `family_id` comme identifiant stable dans `GET /devices`.
- Flux OTP : ajout de `expires_in` et `resend_available_in` aux réponses d'envoi/renvoi.
- Correction du message de `register/verify-code` pour confirmer la vérification (au lieu de "code envoyé").
- Anti-énumération implémenté sur login (timing equalization), forgot-password (même réponse pour email existant/inexistant), register (pas d'OTP si compte existe).
- Nouveaux limiteurs nommés : `otp-send`, `otp-verify`, `login`, `refresh`, `sensitive` avec valeurs configurables.
- Révocation de sessions : changement de mot de passe révoque tous les autres appareils (access + refresh).
- Reset de mot de passe révoque tous les tokens de l'utilisateur.
- Suppression de compte exige le mot de passe actuel et révoque tous les tokens + codes OTP.
- Création de la classe d'exception `ApiException` et handlers dans `bootstrap/app.php` pour les erreurs 4xx avec `code` stable.
- Ajout du champ `code` (snake_case) à toutes les erreurs API : `unauthenticated`, `validation_failed`, `credentials_invalid`, `otp_invalid`, `otp_expired`, `otp_attempts_exceeded`, `verification_token_invalid`, `challenge_token_invalid`, `reset_token_invalid`, `refresh_token_invalid`, `refresh_token_expired`, `password_current_incorrect`, `device_not_found`, `resource_not_found`, `too_many_requests`.
- Header `Retry-After` ajouté aux réponses 429 avec `retry_after` dans le corps JSON.
Migration : `2026_10_09_230956_add_revocation_tracking_to_refresh_tokens_table.php`
Fichiers : `config/auth_api.php`, `.env.example`, `app/Services/OtpService.php`, `app/Models/VerificationCode.php`, `app/Models/RefreshToken.php`, `app/Http/Controllers/Api/V2/Auth/{RefreshTokenController,LoginController,RegisterController,ForgotPasswordController,ProfileController,DeviceController}.php`, `app/Http/Resources/V2/DeviceResource.php`, `app/Exceptions/ApiException.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `routes/api.php`, `lang/en/auth.php`, `database/migrations/2026_10_09_230956_add_revocation_tracking_to_refresh_tokens_table.php`, `docs/api/audit_auth_v2.md`

## [2026-10-09] — Refonte complète de la gestion de la langue et du pays (Tâche 01)
- Refonte du système de détection locale : création de `LocaleResolver` et `LocaleContext` pour une résolution sans effets de bord.
- Correction de l'hypothèse H1 : le profil utilisateur est désormais respecté via `user('sanctum')` dans le resolver.
- Création de l'endpoint public `GET /api/v2/locale` exposant : langue/country résolus, sources, préférences brutes, langues/pays supportés.
- Migration de `PATCH /api/v2/auth/locale` vers `PATCH /api/v2/locale` avec validation corrigée (detection de `null`, `has()` au lieu de `filled()`).
- Suppression des colonnes `country_source` et `language_source` de la table `users` via nouvelle migration.
- Fin de l'auto-persistance : aucune écriture en base pendant la détection.
- Ajout de `Content-Language` sur toutes les réponses API (y compris erreurs 401, 404, 422).
- Ajout du header `Vary: Accept-Language, X-Locale-Override, Authorization`.
- Configuration CORS : `allowed_headers: ['*']`, `exposed_headers: ['Content-Language', 'Retry-After']`.
- Traduction des messages via `__()` avec fichiers `lang/en/locale.php` et `lang/fr/locale.php`.
- Alignement de `register/complete` : `country_id` requis uniquement si >1 pays actif.
- Normalisation des codes de langue (minuscules, trim, partie avant `-`/`_`).
- Priorité de résolution : override > profil > Accept-Language > pays > défaut.
- Création de factories pour `Language`, `Country`, `Currency`.
- Suppression de `LocaleDetectionService` et de l'ancien `LocaleController` (User\LocaleController).
- Nommage de la route `login` pour Sanctum (évite les erreurs 500 de redirect).

Fichiers : `app/Services/Locale/{LocaleContext,LocaleResolver}.php`, `app/Http/Controllers/Api/V2/LocaleController.php`, `app/Http/Resources/V2/LocaleResource.php`, `app/Http/Requests/V2/UpdateLocaleRequest.php`, `app/Http/Requests/V2/RegisterCompleteRequest.php`, `app/Http/Middleware/DetectLocaleMiddleware.php`, `app/Http/Controllers/Api/V2/CountryController.php`, `app/Models/User.php`, `database/migrations/2026_10_09_000000_remove_locale_source_columns_from_users_table.php`, `database/factories/{Language,Country,Currency}Factory.php`, `config/cors.php`, `routes/api.php`, `lang/{en,fr}/locale.php`, `tests/Feature/Api/V2/LocaleTest.php`

## [2026-10-01] — Création de la documentation OpenAPI de l'API v2
- Analyse complète des routes, contrôleurs, requêtes et ressources de l'API v2 pour générer une documentation OpenAPI 3.0.
- Documentation structurée par tags : Authentication, Profile, Devices, Locale, Countries.
- Inclusion de tous les endpoints avec leurs méthodes HTTP, paramètres, corps de requête, et réponses.
- Définition des schémas de données (User, Device, Country, Language) et des erreurs possibles.
- Documentation des headers d'authentification (Bearer token) et de locale (X-Locale-Override, Accept-Language).
- Couverture des endpoints publics (inscription, login, mot de passe oublié, refresh token, pays actifs) et authentifiés (profil, appareils, locale).
- Conversion de la documentation YAML au format JSON pour une meilleure compatibilité avec les outils d'API.
Fichiers : `openapi.yaml`, `openapi.json`, `.ai/changelog.md`

## [2026-09-28] — Création du guide de test final de l'API v2
- Rassemblement des flux d'inscription, connexion, refresh token, pays actifs et préférences de locale dans un seul guide de validation.
- Documentation finale des cas de réussite, d'erreur et de points de vigilance pour les tests manuels dans Insomnia/Postman.
- Alignement du document avec la logique actuelle : routes `/api/v2/auth`, `/api/v2/countries/active`, `X-Locale-Override`, refresh token et validation de locale.
Fichiers : `notes/test_auth_v2_final.md`, `.ai/changelog.md`

## [2026-09-27] — Retrait de la géolocalisation IP des préférences de locale
- La résolution du pays dépend désormais uniquement des préférences enregistrées ou du nombre de pays actifs, sans dépendance ni fallback IP.
- Ajout de l’endpoint public de pays actifs afin que les clients puissent demander un choix manuel lorsque plusieurs pays sont disponibles.
- Les tests et le guide de tests Insomnia couvrent la nouvelle cascade et les préférences manuelles.
Migration : `2026_09_27_164400_make_user_locale_preferences_nullable.php`
Fichiers : `app/Services/LocaleDetectionService.php`, `app/Http/Controllers/Api/V2/CountryController.php`, `database/migrations/2026_09_27_164400_make_user_locale_preferences_nullable.php`, `routes/api.php`, `tests/Feature/LocaleDetectionTest.php`, `testing-locale-api.md`

## [2026-09-27] — Implémentation du système de détection automatique de pays et langue
- Ajout d'un système complet de détection automatique du pays et de la langue avec support des préférences manuelles.
- Ajout des colonnes `country_source` et `language_source` sur la table `users` pour traçabilité manual/auto.
- Création du service `LocaleDetectionService` avec cascade de détection (manual → auto existant → détection IP → fallbacks).
- Création du middleware `DetectLocaleMiddleware` remplaçant l'ancien `SetLocaleMiddleware`.
- Intégration du package `stevebauman/location` pour la géolocalisation IP (ajouté à composer.json sans installation).
- Support du header `X-Locale-Override` pour override client-side de la langue.
- Création de l'endpoint PATCH /api/v2/auth/locale pour la modification manuelle des préférences.
- Support du paramètre `reset_to_auto` pour revenir en mode de détection automatique.
- Gestion des pays inactifs avec fallback sur le pays par défaut et information client via `is_active`.
- Ajout des tests Pest couvrant tous les scénarios de détection et de modification manuelle.
- Mise à jour de `UserResource` pour inclure le champ `is_active` du pays.
- Vérification que le Burkina Faso est bien configuré comme actif dans le seeder existant.
Migration : `2026_09_27_093149_add_locale_source_to_users_table.php`
Fichiers : `composer.json`, `database/migrations/2026_09_27_093149_add_locale_source_to_users_table.php`, `app/Models/User.php`, `app/Services/LocaleDetectionService.php`, `app/Http/Middleware/DetectLocaleMiddleware.php`, `bootstrap/app.php`, `app/Http/Requests/V2/UpdateLocaleRequest.php`, `app/Http/Controllers/Api/V2/User/LocaleController.php`, `routes/api.php`, `app/Http/Resources/V2/UserResource.php`, `tests/Feature/LocaleDetectionTest.php`

## [2026-09-26] — Implémentation du système de Refresh Token
- Ajout d'un système de refresh token pour l'API v2 avec access token valide 1 jour et refresh token valide 1 mois.
- Création de la table `refresh_tokens` et du modèle `RefreshToken` avec gestion de l'expiration et de la révocation.
- Mise à jour des contrôleurs Login et Register pour retourner un refresh token avec l'access token.
- Création du contrôleur `RefreshTokenController` avec endpoint POST /api/v2/auth/refresh pour rafraîchir les tokens.
- Rotation automatique des refresh tokens lors de leur utilisation pour améliorer la sécurité.
- Révocation automatique des refresh tokens lors de la déconnexion et de la suppression d'appareils.
- Configuration de Sanctum pour définir l'expiration des access tokens à 1440 minutes (1 jour).
- Ajout des traductions en anglais et français pour les messages de refresh token.
- Mise à jour du DeviceController pour révoquer les refresh tokens correspondants lors de la déconnexion.
Fichiers : `database/migrations/2026_09_26_151717_create_refresh_tokens_table.php`, `app/Models/RefreshToken.php`, `app/Models/User.php`, `config/sanctum.php`, `app/Http/Requests/V2/RefreshTokenRequest.php`, `app/Http/Controllers/Api/V2/Auth/RefreshTokenController.php`, `app/Http/Controllers/Api/V2/Auth/LoginController.php`, `app/Http/Controllers/Api/V2/Auth/RegisterController.php`, `app/Http/Controllers/Api/V2/Auth/DeviceController.php`, `routes/api.php`, `lang/en/auth.php`, `lang/fr/auth.php`, `notes/test_auth_v2_api_rt.md`

## [2026-09-26] — Limitation des requêtes de l’API d’authentification
- Ajout de quotas Laravel par flux pour limiter les tentatives de connexion, les demandes et vérifications OTP.
- Ajout d’un plafond global de 60 requêtes par minute pour l’API d’authentification, par utilisateur connecté ou adresse IP.
Fichiers : `app/Providers/AppServiceProvider.php`, `routes/api.php`, `tests/Feature/AuthRateLimitingTest.php`
