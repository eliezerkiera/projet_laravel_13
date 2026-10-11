# Rapport d'Analyse Statique - Audit de Conformité OpenAPI

**Date :** 2026-10-10  
**Branch :** develop-api-auth-ai-v2_2  
**Version PHP :** 8.5.5  
**Version Laravel :** 13.17  
**Version Sanctum :** 4.0  
**Version OpenAPI :** 3.0.3

---

## A. Résumé Exécutif

### A.1. État général de la documentation

Le fichier `openapi.yaml` existe à la racine du projet et a été validé avec succès par Redocly CLI (0 erreur, 0 avertissement). La documentation est structurée par tags : `Authentication`, `Profile`, `Devices`, `Locale`, `Countries`.

### A.2. Inventaire des opérations

**Total d'opérations identifiées :** 25 opérations HTTP distinctes (excluant HEAD)

- **Opérations routes réelles :** 25
- **Opérations documentées :** 25
- **Opérations conformes (statique) :** À confirmer par tests HTTP
- **Opérations avec écarts potentiels :** À déterminer après tests

### A.3. Principales observations préliminaires

1. Le fichier OpenAPI est bien structuré et techniquement valide
2. Les routes Laravel correspondent parfaitement aux chemins documentés
3. Les middlewares de rate limiting sont documentés
4. Les schémas de données sont définis dans `components/schemas`
5. Les exemples de réponses existent pour la plupart des endpoints

---

## B. Architecture du Projet

### B.1. Structure du dépôt

- **Framework :** Laravel 13.17
- **Authentification :** Laravel Sanctum 4.0 (tokens Bearer)
- **Version API :** v2 (prefix `/api/v2`)
- **Tests :** Pest PHP 5.1
- **Documentation :** OpenAPI 3.0.3

### B.2. Fichiers de routes

- **Principal :** `routes/api.php`
- **Prefix :** `/api/v2`
- **Groupes :** 
  - `/api/v2/auth/*` : Authentification et profil
  - `/api/v2/locale` : Locale
  - `/api/v2/countries/active` : Pays actifs

### B.3. Contrôleurs API

- `App\Http\Controllers\Api\V2\Auth\DeviceController`
- `App\Http\Controllers\Api\V2\Auth\ForgotPasswordController`
- `App\Http\Controllers\Api\V2\Auth\LoginController`
- `App\Http\Controllers\Api\V2\Auth\ProfileController`
- `App\Http\Controllers\Api\V2\Auth\RefreshTokenController`
- `App\Http\Controllers\Api\V2\Auth\RegisterController`
- `App\Http\Controllers\Api\V2\CountryController`
- `App\Http\Controllers\Api\V2\LocaleController`

### B.4. Middlewares appliqués

- `api` : Middleware API Laravel
- `auth:sanctum` : Authentification Sanctum Bearer
- `throttle:auth-api` : Rate limiting général (60/min)
- `throttle:otp-send` : Rate limiting envoi OTP (1/min, 5/h)
- `throttle:otp-verify` : Rate limiting vérification OTP (5/min)
- `throttle:login` : Rate limiting login (5/min, 20/min/IP)
- `throttle:refresh` : Rate limiting refresh (60/min)
- `throttle:sensitive` : Rate limiting opérations sensibles (5/min)

---

## C. Inventaire des Routes vs OpenAPI

### C.1. Tableau comparatif

| # | Méthode | Chemin Laravel | Chemin OpenAPI | Contrôleur | Auth requise | Statut |
|---|---------|----------------|----------------|-----------|-------------|--------|
| 1 | POST | /api/v2/auth/register/send-code | /auth/register/send-code | RegisterController@sendCode | Non | ✓ Correspond |
| 2 | POST | /api/v2/auth/register/resend-code | /auth/register/resend-code | RegisterController@resendCode | Non | ✓ Correspond |
| 3 | POST | /api/v2/auth/register/verify-code | /auth/register/verify-code | RegisterController@verifyCode | Non | ✓ Correspond |
| 4 | POST | /api/v2/auth/register/complete | /auth/register/complete | RegisterController@complete | Non | ✓ Correspond |
| 5 | POST | /api/v2/auth/login | /auth/login | LoginController@login | Non | ✓ Correspond |
| 6 | POST | /api/v2/auth/login/resend-code | /auth/login/resend-code | LoginController@resendCode | Non | ✓ Correspond |
| 7 | POST | /api/v2/auth/login/verify-code | /auth/login/verify-code | LoginController@verifyCode | Non | ✓ Correspond |
| 8 | POST | /api/v2/auth/forgot-password/send-code | /auth/forgot-password/send-code | ForgotPasswordController@sendCode | Non | ✓ Correspond |
| 9 | POST | /api/v2/auth/forgot-password/resend-code | /auth/forgot-password/resend-code | ForgotPasswordController@resendCode | Non | ✓ Correspond |
| 10 | POST | /api/v2/auth/forgot-password/verify-code | /auth/forgot-password/verify-code | ForgotPasswordController@verifyCode | Non | ✓ Correspond |
| 11 | POST | /api/v2/auth/forgot-password/reset | /auth/forgot-password/reset | ForgotPasswordController@resetPassword | Non | ✓ Correspond |
| 12 | POST | /api/v2/auth/verify-pending-email | /auth/verify-pending-email | ProfileController@verifyPendingEmailGuest | Non | ✓ Correspond |
| 13 | POST | /api/v2/auth/refresh | /auth/refresh | RefreshTokenController@refresh | Non | ✓ Correspond |
| 14 | GET | /api/v2/auth/me | /auth/me | ProfileController@me | Oui | ✓ Correspond |
| 15 | PUT | /api/v2/auth/profile | /auth/profile | ProfileController@update | Oui | ✓ Correspond |
| 16 | POST | /api/v2/auth/profile/verify-email | /auth/profile/verify-email | ProfileController@verifyEmail | Oui | ✓ Correspond |
| 17 | PUT | /api/v2/auth/password | /auth/password | ProfileController@changePassword | Oui | ✓ Correspond |
| 18 | DELETE | /api/v2/auth/account | /auth/account | ProfileController@destroy | Oui | ✓ Correspond |
| 19 | POST | /api/v2/auth/logout | /auth/logout | DeviceController@logout | Oui | ✓ Correspond |
| 20 | GET | /api/v2/auth/devices | /auth/devices | DeviceController@index | Oui | ✓ Correspond |
| 21 | DELETE | /api/v2/auth/devices/{id} | /auth/devices/{id} | DeviceController@destroy | Oui | ✓ Correspond |
| 22 | DELETE | /api/v2/auth/devices | /auth/devices | DeviceController@destroyOthers | Oui | ✓ Correspond |
| 23 | GET | /api/v2/locale | /locale | LocaleController@show | Non | ✓ Correspond |
| 24 | GET | /api/v2/countries/active | /countries/active | CountryController@index | Non | ✓ Correspond |
| 25 | PATCH | /api/v2/locale | /locale | LocaleController@update | Oui | ✓ Correspond |

**Conclusion :** Toutes les 25 routes sont documentées dans OpenAPI. Aucune route manquante ou surnuméraire détectée au niveau des chemins et méthodes HTTP.

---

## D. Analyse des Validations et Requêtes

### D.1. Observations générales

Les Form Requests sont utilisées pour la validation dans la plupart des endpoints. Les règles de validation doivent être comparées avec les schémas OpenAPI.

### D.2. Endpoints nécessitant une vérification approfondie

Les endpoints suivants nécessitent une vérification détaillée des champs de requête :

1. **POST /auth/register/complete** - Champs multiples, validation complexe
2. **POST /auth/login/verify-code** - Paramètres de challenge et OTP
3. **POST /auth/forgot-password/reset** - Token de reset et mot de passe
4. **PUT /auth/profile** - Mise à jour partielle du profil
5. **PUT /auth/password** - Changement de mot de passe avec confirmation
6. **DELETE /auth/account** - Suppression avec mot de passe
7. **PATCH /locale** - Mise à jour des préférences de locale

### D.3. Points à vérifier

- Les champs obligatoires (required) dans OpenAPI correspondent-ils aux Form Requests ?
- Les types de données (string, integer, boolean) sont-ils corrects ?
- Les contraintes (minLength, maxLength, format) sont-elles documentées ?
- Les champs nullable sont-ils correctement identifiés ?
- Les règles conditionnelles sont-elles documentées ?

---

## E. Analyse des Réponses

### E.1. Codes HTTP documentés

Les codes HTTP suivants sont documentés dans OpenAPI :
- 200 OK (succès)
- 201 Created (création)
- 401 Unauthorized (non authentifié)
- 403 Forbidden (accès refusé)
- 404 Not Found (ressource introuvable)
- 422 Unprocessable Entity (validation)
- 429 Too Many Requests (rate limiting)

### E.2. Structure des réponses

Les schémas de réponse sont définis dans `components/schemas` :
- `User` : Utilisateur complet
- `Device` : Appareil connecté
- `AuthTokenResponse` : Réponse avec tokens
- `LocaleContext` : Contexte de locale
- `Country` / `CountryDetail` : Pays
- `Language` / `LanguageWithSource` : Langue
- `ErrorResponse` / `ValidationErrorResponse` : Erreurs
- `RateLimitErrorResponse` : Rate limiting

### E.3. Points à vérifier

- Les réponses réelles correspondent-elles aux schémas ?
- Les champs optionnels sont-ils correctement identifiés ?
- Les exemples de réponse sont-ils réalistes ?
- Les codes d'erreur (`code`) sont-ils corrects ?
- Les messages d'erreur sont-ils traduits ?

---

## F. Analyse de l'Authentification Sanctum

### F.1. Mécanisme utilisé

L'API utilise **Laravel Sanctum avec tokens Bearer** :
- Middleware : `auth:sanctum`
- Header : `Authorization: Bearer <token>`
- Type de token : Token personnel Sanctum
- Refresh tokens : Système maison avec rotation

### F.2. Routes publiques

Les routes suivantes ne nécessitent pas d'authentification :
- Tous les endpoints d'inscription (`register/*`)
- Tous les endpoints de login (`login/*`)
- Tous les endpoints de mot de passe oublié (`forgot-password/*`)
- `POST /auth/verify-pending-email`
- `POST /auth/refresh`
- `GET /locale`
- `GET /countries/active`

### F.3. Routes protégées

Les routes suivantes nécessitent l'authentification Sanctum :
- `GET /auth/me`
- `PUT /auth/profile`
- `POST /auth/profile/verify-email`
- `PUT /auth/password`
- `DELETE /auth/account`
- `POST /auth/logout`
- `GET /auth/devices`
- `DELETE /auth/devices/{id}`
- `DELETE /auth/devices`
- `PATCH /locale`

### F.4. Vérification OpenAPI

Le schéma de sécurité `BearerAuth` est défini dans `components/securitySchemes` et appliqué correctement aux routes protégées via les déclarations `security`.

---

## G. Validation Technique OpenAPI

### G.1. Validation avec Redocly CLI

**Commande :** `npx --yes @redocly/cli lint openapi.yaml`  
**Résultat :** ✓ Validé (0 erreur, 0 avertissement)

### G.2. Structure du document

- **Version OpenAPI :** 3.0.3
- **Serveurs :** Défini (`/api/v2`)
- **Tags :** 5 tags (Authentication, Profile, Devices, Locale, Countries)
- **Composants :** Schémas, paramètres, headers, securitySchemes, responses
- **Références $ref :** Validées par le linter

### G.3. Points forts

- Schémas réutilisables bien structurés
- Exemples nommés pour les erreurs
- Documentation des rate limiters
- Tags cohérents
- Description détaillée des endpoints

---

## H. Écarts Potentiels à Vérifier par Tests HTTP

### H.1. Validation des champs de requête

Les écarts potentiels suivants nécessitent une vérification par tests HTTP :

| Endpoint | Champ | Potentiel écart | Type de vérification |
|----------|-------|-----------------|---------------------|
| POST /register/complete | country_id | Requis si >1 pays actif | Validation conditionnelle |
| POST /register/complete | language_id | Nullable dans code | Nullable vs required |
| POST /login/verify-code | device_name | Optionnel dans code | Présence/absence |
| PUT /auth/profile | country_id, language_id | Retirés du code | Champs retirés |
| PATCH /locale | reset_to_auto | Exclusif avec autres champs | Règle de validation |

### H.2. Réponses d'erreur

Les codes d'erreur suivants doivent être vérifiés :
- `otp_expired` : Vérifier le message exact
- `otp_max_attempts` : Vérifier le comportement
- `refresh_token_expired` : Vérifier le message exact
- `invalid_token` : Vérifier les contextes d'utilisation
- `device_not_found` : Vérifier sur DELETE /devices/{id}

### H.3. Authentification

Les scénarios suivants doivent être testés :
- Accès sans token sur route protégée (401 attendu)
- Token invalide (401 attendu)
- Token expiré (401 attendu)
- Token révoqué (401 attendu)

### H.4. Rate Limiting

Les limiteurs suivants doivent être testés :
- `auth-api` (60/min)
- `otp-send` (1/min, 5/h)
- `otp-verify` (5/min)
- `login` (5/min, 20/min/IP)
- `refresh` (60/min)
- `sensitive` (5/min)

---

## I. Recommandations sur l'Ordre d'Exécution des Tests

### I.1. Priorité 1 - Routes publiques (sans authentification)

1. **TEST-001** : GET /locale (contexte initial)
2. **TEST-002** : GET /countries/active (liste des pays)
3. **TEST-003** : POST /auth/register/send-code (inscription - envoi code)
4. **TEST-004** : POST /auth/register/verify-code (inscription - vérification)
5. **TEST-005** : POST /auth/register/complete (inscription - finalisation)
6. **TEST-006** : POST /auth/login (login - étape 1)
7. **TEST-007** : POST /auth/login/verify-code (login - étape 2)
8. **TEST-008** : POST /auth/refresh (refresh token)

### I.2. Priorité 2 - Routes protégées (avec authentification)

9. **TEST-009** : GET /auth/me (profil utilisateur)
10. **TEST-010** : PUT /auth/profile (modification profil)
11. **TEST-011** : POST /auth/profile/verify-email (vérification email)
12. **TEST-012** : PUT /auth/password (changement mot de passe)
13. **TEST-013** : GET /auth/devices (liste appareils)
14. **TEST-014** : DELETE /auth/devices/{id} (déconnexion appareil)
15. **TEST-015** : DELETE /auth/devices (déconnexion autres)
16. **TEST-016** : POST /auth/logout (déconnexion)
17. **TEST-017** : PATCH /locale (mise à jour locale)
18. **TEST-018** : DELETE /auth/account (suppression compte)

### I.3. Priorité 3 - Scénarios d'erreur

19. **TEST-019** : POST /auth/forgot-password/send-code (mot de passe oublié)
20. **TEST-020** : POST /auth/forgot-password/verify-code (vérification)
21. **TEST-021** : POST /auth/forgot-password/reset (réinitialisation)
22. **TEST-022** : POST /auth/verify-pending-email (email pending)
23. **TEST-023** : POST /auth/register/resend-code (renvoi)
24. **TEST-024** : POST /auth/login/resend-code (renvoi login)
25. **TEST-025** : POST /auth/forgot-password/resend-code (renvoi forgot)

### I.4. Priorité 4 - Cas d'erreur et edge cases

26. **TEST-026** : Accès sans authentification sur route protégée
27. **TEST-027** : Token invalide
28. **TEST-028** : Validation des champs obligatoires manquants
29. **TEST-029** : Types de données incorrects
30. **TEST-030** : Rate limiting (429)
31. **TEST-031** : Ressource inexistante (404)
32. **TEST-032** : Device non trouvé (device_not_found)

---

## J. Conclusion de l'Analyse Statique

### J.1. Points forts identifiés

1. **Couverture complète** : Toutes les 25 routes sont documentées
2. **Validité technique** : OpenAPI validé sans erreur
3. **Structure cohérente** : Tags et schémas bien organisés
4. **Exemples présents** : Réponses d'erreur avec exemples nommés
5. **Security documenté** : Authentification Sanctum correctement décrite

### J.2. Points à confirmer par tests HTTP

1. **Validations de champs** : Correspondance des règles Laravel vs OpenAPI
2. **Réponses réelles** : Structure JSON exacte des réponses
3. **Codes d'erreur** : Messages exacts et comportements
4. **Rate limiting** : Valeurs réelles des limiteurs
5. **Nullable vs required** : Champs optionnels corrects
6. **Authentification** : Comportement exact des erreurs 401

### J.3. Prochaine étape

Le plan de tests détaillé Insomnia sera présenté dans le fichier `docs/api-audit/02-plan-tests-insomnia.md`.

---

**Fin du rapport d'analyse statique**
