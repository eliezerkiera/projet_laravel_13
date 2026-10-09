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
