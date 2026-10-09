# Audit de l'API d'authentification V2

Généré le 2026-10-09 à partir de l'analyse du code existant.

---

## Routes d'authentification

### Registration Flow

| Méthode + Chemin | Auth requise | Contrôleur@Méthode | FormRequest | Limite de débit | Corps attendu | Réponses |
|---|---|---|---|---|---|---|
| POST /api/v2/auth/register/send-code | Non | RegisterController@sendCode | RegisterSendCodeRequest | auth-otp-request (5/min) | `email` (required, email) | 200: `{message, email}`<br>422: validation errors |
| POST /api/v2/auth/register/resend-code | Non | RegisterController@resendCode | RegisterSendCodeRequest | auth-otp-request (5/min) | `email` (required, email) | 200: `{message, email}`<br>422: validation errors |
| POST /api/v2/auth/register/verify-code | Non | RegisterController@verifyCode | RegisterVerifyCodeRequest | auth-otp-verification (5/min) | `email`, `code` (required) | 200: `{message, verification_token, email}`<br>422: `{code: 'otp_invalid'/'otp_expired'/'otp_max_attempts'}` |
| POST /api/v2/auth/register/complete | Non | RegisterController@complete | RegisterCompleteRequest | auth-otp-verification (5/min) | `verification_token`, `first_name`, `last_name`, `password`, `country_id`, `language_id` | 201: `{message, access_token, refresh_token, token_type, user}`<br>422: validation / invalid token |

### Login & 2FA Flow

| Méthode + Chemin | Auth requise | Contrôleur@Méthode | FormRequest | Limite de débit | Corps attendu | Réponses |
|---|---|---|---|---|---|---|
| POST /api/v2/auth/login | Non | LoginController@login | LoginRequest | auth-login (5/min) + auth-api | `email`, `password` (required) | 200: `{message, challenge_token, email}`<br>403: pending email verification<br>422: `{email: 'credentials_invalid'}` |
| POST /api/v2/auth/login/resend-code | Non | LoginController@resendCode | (inline validation) | auth-otp-request (5/min) | `challenge_token` ou `email` (nullable) | 200: `{message, challenge_token}`<br>422: `{challenge_token: 'invalid_token'}` |
| POST /api/v2/auth/login/verify-code | Non | LoginController@verifyCode | LoginVerifyCodeRequest | auth-otp-verification (5/min) | `challenge_token`, `code`, `device_name` (optional) | 200: `{message, access_token, refresh_token, token_type, user}`<br>422: `{challenge_token/code: 'invalid_token'/'otp_expired'/'otp_max_attempts'/'otp_invalid'}` |

### Password Reset Flow

| Méthode + Chemin | Auth requise | Contrôleur@Méthode | FormRequest | Limite de débit | Corps attendu | Réponses |
|---|---|---|---|---|---|---|
| POST /api/v2/auth/forgot-password/send-code | Non | ForgotPasswordController@sendCode | ForgotPasswordSendCodeRequest | auth-otp-request (5/min) | `email` (required, email) | 200: `{message, email}`<br>422: validation errors |
| POST /api/v2/auth/forgot-password/resend-code | Non | ForgotPasswordController@resendCode | ForgotPasswordSendCodeRequest | auth-otp-request (5/min) | `email` (required, email) | 200: `{message, email}`<br>422: validation errors |
| POST /api/v2/auth/forgot-password/verify-code | Non | ForgotPasswordController@verifyCode | ForgotPasswordVerifyCodeRequest | auth-otp-verification (5/min) | `email`, `code` (required) | 200: `{message, reset_token, email}`<br>422: OTP errors |
| POST /api/v2/auth/forgot-password/reset | Non | ForgotPasswordController@resetPassword | ResetPasswordRequest | auth-otp-verification (5/min) | `token`, `password`, `password_confirmation` (required) | 200: `{message}`<br>422: `{message: 'invalid_token'}` |

### Authenticated Endpoints

| Méthode + Chemin | Auth requise | Contrôleur@Méthode | FormRequest | Limite de débit | Corps attendu | Réponses |
|---|---|---|---|---|---|---|
| GET /api/v2/auth/me | Sanctum | ProfileController@me | - | auth-api (60/min) | - | 200: `{user}` |
| PUT /api/v2/auth/profile | Sanctum | ProfileController@update | UpdateProfileRequest | auth-api (60/min) | `first_name`, `last_name`, `email`, `country_id`, `language_id` | 200: `{message, user}`<br>422: validation / email already used |
| POST /api/v2/auth/profile/verify-email | Sanctum | ProfileController@verifyEmail | VerifyEmailChangeRequest | auth-api (60/min) | `code` (required) | 200: `{message, user}`<br>422: `{code: 'invalid_token'}` |
| PUT /api/v2/auth/password | Sanctum | ProfileController@changePassword | ChangePasswordRequest | auth-api (60/min) | `password`, `password_confirmation`, `current_password` (required) | 200: `{message}`<br>422: validation |
| DELETE /api/v2/auth/account | Sanctum | ProfileController@destroy | DeleteAccountRequest | auth-api (60/min) | `password` (required) | 200: `{message}`<br>422: validation |
| POST /api/v2/auth/logout | Sanctum | DeviceController@logout | - | auth-api (60/min) | - | 200: `{message}` |
| GET /api/v2/auth/devices | Sanctum | DeviceController@index | - | auth-api (60/min) | - | 200: `{devices: [{id, name, is_current, last_used_at, created_at}]}` |
| DELETE /api/v2/auth/devices/{id} | Sanctum | DeviceController@destroy | - | auth-api (60/min) | - | 200: `{message}`<br>404: `{message: 'device_not_found'}` |
| DELETE /api/v2/auth/devices | Sanctum | DeviceController@destroyOthers | - | auth-api (60/min) | - | 200: `{message}` |

### Other Auth Routes

| Méthode + Chemin | Auth requise | Contrôleur@Méthode | FormRequest | Limite de débit | Corps attendu | Réponses |
|---|---|---|---|---|---|---|
| POST /api/v2/auth/refresh | Non | RefreshTokenController@refresh | RefreshTokenRequest | auth-api (60/min) | `refresh_token` (required) | 200: `{message, access_token, refresh_token, token_type, user}`<br>422: `{refresh_token: 'invalid_refresh_token'/'refresh_token_expired'}` |
| POST /api/v2/auth/verify-pending-email | Non | ProfileController@verifyPendingEmailGuest | (inline validation) | auth-otp-verification (5/min) | `email`, `code` (required) | 200: `{message}`<br>422: `{email: 'invalid_token'}` |

---

## Modèle de données

### OTP (VerificationCode)

**Table :** `verification_codes`

**Colonnes :**
- `id` (primary key)
- `user_id` (nullable, foreign key to users)
- `email` (indexed)
- `code` (string, 6 chars) - **stocké en clair**
- `type` (indexed) - valeurs : `register`, `login`, `password_reset`, `email_change`
- `token` (nullable, unique, 64 chars) - temporary verification token
- `data` (json, nullable) - métadonnées optionnelles
- `attempts` (unsigned tinyint, default 0)
- `expires_at` (timestamp)
- `verified_at` (timestamp, nullable)
- `created_at`, `updated_at`

**Durée de vie effective :**
- Code OTP : 10 minutes (constante `OtpService::EXPIRATION_MINUTES`)
- Verification token : 15 minutes après vérification (hardcoded dans `OtpService::verifyCode`)

**Nombre d'essais max :** 5 (constante `VerificationCode::MAX_ATTEMPTS`)

**Isolation par purpose :** OUI - les `type` sont isolés (query inclut `where('type', $type)`)

**Comportement :**
- Un seul code actif par couple (email, type) - le renvoi invalide le précédent (delete dans `OtpService::generateAndSend`)
- Comparaison avec `hash_equals` (utilisé dans `OtpService::verifyCode`)
- Code consommé après succès (delete dans `OtpService::consume`)

### Refresh Tokens

**Table :** `refresh_tokens`

**Colonnes :**
- `id` (primary key)
- `user_id` (foreign key to users, cascade on delete)
- `token` (string, 64 chars, unique)
- `device_name` (string)
- `expires_at` (timestamp, indexed)
- `revoked` (boolean, default false)
- `created_at`, `updated_at`

**Durée de vie effective :** 1 mois (`now()->addMonth()` dans tous les contrôleurs)

**Lien avec access tokens :**
- Le refresh token est stocké dans une table séparée
- L'access token Sanctum est dans `personal_access_tokens`
- Lien par `device_name` (pas de foreign key direct)
- Le refresh révoque TOUS les access tokens existants (`$user->tokens()->delete()`)

**Notion d'appareil :**
- Identifié par `device_name` (string, non unique)
- `GET /devices` retourne les `personal_access_tokens` (par leur `id`)
- L'`id` exposé est `personal_access_tokens.id`
- **Problème :** l'`id` change à chaque refresh (nouveau token créé)
- **Problème :** un refresh crée un doublon dans `GET /devices` (ancien token révoqué mais encore visible avant refresh)

**Révocation :**
- Colonnes : `revoked` (boolean)
- **Manque :** pas de `revoked_reason`, pas de `revoked_at`, pas de `family_id`
- Impossible de distinguer rotation vs logout vs autre

### Access Tokens (Sanctum)

**Table :** `personal_access_tokens` (standard Sanctum)

**Configuration :**
- `config('sanctum.expiration')` = 1440 minutes (1 jour)
- **Mais :** les tokens sont créés SANS expiration explicite (`createToken($name)` sans paramètre d'expiration)
- Sanctum applique l'expiration de config à tous les tokens automatiquement

**Durée de vie effective :** 1 jour (config sanctum.expiration)

### Jetons intermédiaires

**Types :**
- `verification_token` (register/verify-code) - stocké dans `verification_codes.token`
- `challenge_token` (login) - stocké dans `verification_codes.token`
- `reset_token` (forgot-password/verify-code) - stocké dans `verification_codes.token`

**Stockage :** Tous dans la colonne `token` de `verification_codes` (ligne en base, pas JWT, pas cache)

**Durée de vie :** 15 minutes après vérification (hardcoded)

---

## Réponses aux questions de l'audit

### 1. L'access token Sanctum expire-t-il réellement ?

**OUI**, via `config('sanctum.expiration')` = 1440 minutes (1 jour).
MAIS les tokens sont créés sans expiration explicite dans le code, donc Sanctum applique la config globalement à tous les tokens.

### 2. Un appareil correspond-il à un `personal_access_tokens.id` ? Son `id` change-t-il à chaque refresh ? Un refresh crée-t-il un doublon dans `GET /devices` ?

- Appareil = `personal_access_tokens.id` (exposé dans `GET /devices`)
- **OUI**, l'`id` change à chaque refresh (nouveau token créé, ancien supprimé)
- **OUI**, un refresh crée un doublon temporaire (ancien token révoqué mais `DeviceResource` utilise `$request->user()->tokens()->latest('created_at')->get()` qui peut inclure le token qui vient d'être révoqué)

### 3. Le refresh token révoqué par rotation est-il distinguable d'un token révoqué par logout ?

**NON.** Seule la colonne `revoked` (boolean) existe. Impossible de connaître la raison de la révocation.

### 4. Quelle est la différence réelle entre `POST /auth/verify-pending-email` (publique) et `POST /auth/profile/verify-email` (protégée) ?

- **verify-pending-email (publique)** : pour un utilisateur déconnecté qui a initié un changement d'email puis s'est déconnecté. Trouve l'utilisateur par `pending_email` OU `email` avec `pending_email` non null.
- **profile/verify-email (protégée)** : pour un utilisateur connecté. Utilise l'utilisateur authentifié (`$request->user()`) et son `pending_email`.

**Sécurité de la route publique :** 
- Exige l'email ET le code OTP
- L'OTP est isolé par type (`TYPE_EMAIL_CHANGE`)
- Soumis au limiteur `auth-otp-verification`
- **Potentiellement vulnérable** : si un attaquant connaît l'email pending et devine/reçoit le code (interception email), il peut valider le changement d'email sans être authentifié.

### 5. Les OTP de purposes différents sont-ils isolés ?

**OUI.** Chaque query inclut `where('type', $type)` et les constantes de type sont distinctes.

### 6. Les emails OTP sont-ils envoyés de manière synchrone ?

**OUI.** `Notification::route('mail', $email)->notify(...)` est synchrone par défaut dans Laravel (sauf si queue configurée).

---

## Limites de débit actuelles

| Limiteur | Définition (AppServiceProvider) | Valeur |
|---|---|---|
| auth-api | 60/min par user_id ou IP | 60/min |
| auth-login | 5/min par email+IP ou IP seule | 5/min |
| auth-otp-request | 5/min par email/challenge_token+IP ou IP | 5/min |
| auth-otp-verification | 5/min par email/token+IP ou IP | 5/min |

**Note :** Les limiteurs sont nommés mais les valeurs sont en dur dans `AppServiceProvider`, pas dans la config.

---

## Problèmes détectés

### Critiques

1. **Révocation de refresh token non traçable** : Pas de `revoked_reason`, impossible de distinguer rotation/logout/réutilisation
2. **Identité d'appareil instable** : L'`id` de device change à chaque refresh, ce qui casse le suivi d'appareil côté front
3. **Doublon dans GET /devices** : Le refresh peut créer temporairement des doublons dans la liste des appareils
4. **Access token créé sans expiration explicite** : Dépend uniquement de la config Sanctum globale
5. **Emails OTP synchrones** : Le temps de réponse dépend de l'envoi d'email
6. **Anti-énumération absent** :
   - Login renvoie une erreur différente pour email inconnu vs mauvais mot de passe
   - forgot-password/send-code ne renvoie pas le même message pour email existant/inexistant
   - register/send-code pour email déjà inscrit n'envoie pas de notification "compte existant"

### Modérés

7. **Route publique verify-pending-email potentiellement vulnérable** : Permet de valider un changement d'email sans auth avec seulement email+code
8. **Pas de family_id sur refresh tokens** : Impossible de suivre la "famille" de tokens issus d'un appareil
9. **Pas de détection de réutilisation de refresh token** : Rotation sans protection contre réutilisation
10. **Révocation de sessions incomplète** :
    - Changement de mot de passe révoque les access tokens mais pas les refresh tokens (sauf current device)
    - Suppression de compte révoque les access tokens mais pas les refresh tokens

### Mineurs

11. **Messages de validation sans `code` stable** : Les erreurs 4xx n'ont pas de champ `code` snake_case
12. **Pas de `expires_in` dans les réponses de token** : Le front ne sait pas quand les tokens expirent
13. **UserResource non uniforme** : `/refresh` renvoie un user réduit (id, email uniquement) au lieu du UserResource complet
14. **Valeurs de délai en dur** : Les durées (10 min OTP, 1 mois refresh, etc.) sont en dur dans le code, pas dans la config
15. **Suppression de compte sans vérification de mot de passe** : Actuellement `DeleteAccountRequest` ne semble pas exiger le mot de passe (à vérifier)

---

## Observations supplémentaires

- Les messages d'API utilisent `__()` avec des fichiers de traduction `fr` et `en` (lang/en/auth.php, lang/fr/auth.php)
- CORS est déjà configuré avec `Content-Language` et `Retry-After` dans `exposed_headers`
- La tâche 01 (locale/pays) est déjà terminée
- Les tests existent mais nécessitent vérification
