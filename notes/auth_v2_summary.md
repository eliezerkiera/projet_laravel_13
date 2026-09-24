# Résumé du Système d'Authentification API v2 (Laravel 13 & Sanctum)

Ce document résume l'implémentation complète du système d'authentification REST API v2 développé selon les exigences du fichier `notes/auth_prompt.md`.

---

## 1. Vue d'Ensemble

- **Version de l'API** : v2 (toutes les routes sous le préfixe `/api/v2/auth/...`).
- **Framework & Packages** : Laravel 13, Laravel Sanctum v4.
- **Sécurité & OTP** :
  - Codes numériques à 6 chiffres générés de manière cryptographiquement sûre (`random_int(100000, 999999)`).
  - Validité de 10 minutes par code.
  - Cooldown de 60 secondes entre deux renvois (anti-spam).
  - Limitation à 5 tentatives erronées avant invalidation du code (anti-brute-force).
  - Émission de jetons temporaires sécurisés (`verification_token`, `challenge_token`, `reset_token`) valides 15 minutes pour sceller chaque étape.
- **Gestion des sessions** : Tokens d'accès Sanctum avec nom d'appareil (`device_name`).
- **Suppression** : Soft Delete (`deleted_at`) sur le modèle `User`.
- **Internationalisation (i18n)** : Priorité par défaut à l'anglais (`en`), support complet du français (`fr`), commutable via l'en-tête HTTP `Accept-Language`.
- **Qualité du code** : Entièrement typé (PHP 8.3+), commenté en anglais, et formaté avec Laravel Pint.

---

## 2. Tableau des Endpoints Développés

| Méthode | Route | Description | Authentifié ? |
| :--- | :--- | :--- | :---: |
| **POST** | `/api/v2/auth/register/send-code` | Étape 1 : Envoi du code OTP à l'email pour inscription | Non |
| **POST** | `/api/v2/auth/register/resend-code` | Étape 1b : Renvoi du code d'inscription (cooldown 60s) | Non |
| **POST** | `/api/v2/auth/register/verify-code` | Étape 2 : Vérification du code OTP $\rightarrow$ renvoie `verification_token` | Non |
| **POST** | `/api/v2/auth/register/complete` | Étape 3 : Finalisation (`verification_token`, profil, mdp) $\rightarrow$ token Sanctum | Non |
| **POST** | `/api/v2/auth/login` | Étape 1 : Vérification email/mot de passe $\rightarrow$ envoi OTP 2FA & `challenge_token` | Non |
| **POST** | `/api/v2/auth/login/resend-code` | Étape 1b : Renvoi du code 2FA | Non |
| **POST** | `/api/v2/auth/login/verify-code` | Étape 2 : Validation code 2FA $\rightarrow$ délivrance du token Sanctum | Non |
| **POST** | `/api/v2/auth/forgot-password/send-code` | Étape 1 : Envoi code OTP de réinitialisation de mot de passe | Non |
| **POST** | `/api/v2/auth/forgot-password/resend-code` | Étape 1b : Renvoi du code de réinitialisation | Non |
| **POST** | `/api/v2/auth/forgot-password/verify-code` | Étape 2 : Vérification code $\rightarrow$ renvoie `reset_token` | Non |
| **POST** | `/api/v2/auth/forgot-password/reset` | Étape 3 : Saisie nouveau mot de passe avec `reset_token` & révocation tokens | Non |
| **POST** | `/api/v2/auth/verify-pending-email` | Validation du nouvel email si l'utilisateur s'est déconnecté | Non |
| **GET**  | `/api/v2/auth/me` | Récupération du profil connecté (avec pays et langue) | **Oui** |
| **PUT**  | `/api/v2/auth/profile` | Modification profil (`first_name`, `last_name`, `country_id`, `language_id`, `email`) | **Oui** |
| **POST** | `/api/v2/auth/profile/verify-email` | Validation OTP pour appliquer le changement d'email | **Oui** |
| **PUT**  | `/api/v2/auth/password` | Changement de mot de passe (vérifie le mot de passe actuel) | **Oui** |
| **DELETE**| `/api/v2/auth/account` | Suppression douce (Soft Delete) après saisie du mot de passe | **Oui** |
| **GET**  | `/api/v2/auth/devices` | Liste des appareils connectés avec indicateur `is_current` | **Oui** |
| **DELETE**| `/api/v2/auth/devices/{id}` | Déconnexion d'un appareil spécifique | **Oui** |
| **DELETE**| `/api/v2/auth/devices` | Déconnexion de tous les autres appareils sauf l'actuel | **Oui** |
| **POST** | `/api/v2/auth/logout` | Déconnexion de l'appareil courant | **Oui** |

---

## 3. Règle Spécifique sur le Changement d'Email

- Lorsqu'un utilisateur authentifié modifie son adresse email via `PUT /api/v2/auth/profile` :
  1. L'ancien email reste actif pour l'instant.
  2. Le nouvel email est stocké dans la colonne `pending_email` de la table `users`.
  3. Un code OTP à 6 chiffres est expédié **à la nouvelle adresse email**.
  4. **Si l'utilisateur tente de se reconnecter alors qu'un changement d'email est en attente**, la connexion est bloquée (`403 Forbidden`) avec le message :
     `"You must verify your new email address before logging in."`
  5. Pour finaliser la modification, l'utilisateur soumet le code soit via `POST /api/v2/auth/profile/verify-email` (s'il est encore connecté), soit via `POST /api/v2/auth/verify-pending-email` (s'il est déconnecté). Une fois vérifié, l'email est définitivement mis à jour et `pending_email` est réinitialisé à `null`.

---

## 4. Fichiers et Architecture du Code

- **Modèles & Base de données** :
  - `app/Models/User.php` : Utilise `HasApiTokens`, `SoftDeletes`, `pending_email`, accesseurs, relations typées.
  - `app/Models/VerificationCode.php` : Modèle dédié aux OTP avec constantes de type et méthodes helpers (`isExpired()`, `hasExceededAttempts()`, etc.).
  - `database/migrations/2026_09_24_160446_add_pending_email_to_users_table.php` : Ajout `pending_email` et `softDeletes` conditionnel.
  - `database/migrations/2026_09_24_160808_create_verification_codes_table.php` : Table des codes OTP.
- **Services & Notifications** :
  - `app/Services/OtpService.php` : Logique centrale de génération, envoi, cooldown, vérification, émission et consommation de tokens temporaires.
  - `app/Notifications/SendOtpNotification.php` : Notification email bilingue envoyant le code OTP au destinataire.
- **Validation (Form Requests)** dans `app/Http/Requests/V2/` :
  - `RegisterSendCodeRequest`, `RegisterVerifyCodeRequest`, `RegisterCompleteRequest`
  - `LoginRequest`, `LoginVerifyCodeRequest`
  - `ForgotPasswordSendCodeRequest`, `ForgotPasswordVerifyCodeRequest`, `ResetPasswordRequest`
  - `UpdateProfileRequest`, `VerifyEmailChangeRequest`
  - `ChangePasswordRequest`, `DeleteAccountRequest`
- **Ressources API** dans `app/Http/Resources/V2/` :
  - `UserResource.php` : Formatage propre du profil utilisateur avec relations pays et langue.
  - `DeviceResource.php` : Formatage des sessions/appareils avec statut `is_current`.
- **Contrôleurs** dans `app/Http/Controllers/Api/V2/Auth/` :
  - `RegisterController.php`
  - `LoginController.php`
  - `ForgotPasswordController.php`
  - `ProfileController.php`
  - `DeviceController.php`
- **Internationalisation & Middleware** :
  - `app/Http/Middleware/SetLocaleMiddleware.php` : Détection de la langue via l'en-tête `Accept-Language` (défaut : anglais).
  - `lang/en/auth.php` et `lang/fr/auth.php` : Fichiers de traductions des messages et emails.
- **Routes** :
  - `routes/api.php`
