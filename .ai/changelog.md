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
