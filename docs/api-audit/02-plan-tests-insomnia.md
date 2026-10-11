# Plan de Tests Manuels Insomnia - API v2

**Date :** 2026-10-10  
**Version API :** v2  
**Objectif :** Valider la conformité entre l'implémentation Laravel et la documentation OpenAPI

---

## A. Configuration de l'Environnement Insomnia

### A.1. Variables d'environnement

Créer un environnement Insomnia avec les variables suivantes :

| Variable | Valeur suggérée | Description |
|----------|-----------------|-------------|
| `base_url` | `http://127.0.0.1:8000` | URL de base de l'API (à adapter selon votre environnement) |
| `api_version` | `v2` | Version de l'API |
| `auth_url` | `{{base_url}}/api/{{api_version}}/auth` | URL de base des endpoints d'authentification |
| `locale_url` | `{{base_url}}/api/{{api_version}}/locale` | URL de l'endpoint locale |
| `countries_url` | `{{base_url}}/api/{{api_version}}/countries/active` | URL de l'endpoint countries |
| `access_token` | `""` | Token d'accès (sera rempli après login) |
| `refresh_token` | `""` | Token de refresh (sera rempli après login) |
| `challenge_token` | `""` | Token de challenge (sera rempli après login étape 1) |
| `verification_token` | `""` | Token de vérification (sera rempli après register/verify-code) |
| `reset_token` | `""` | Token de reset (sera rempli après forgot-password/verify-code) |
| `test_email` | `test.user@example.com` | Email de test |
| `test_password` | `Password123!` | Mot de passe de test |

### A.2. Headers par défaut

Ajouter les headers suivants aux requêtes :

| Header | Valeur | Description |
|--------|-------|-------------|
| `Accept` | `application/json` | Nécessaire pour éviter les réponses HTML |
| `Content-Type` | `application/json` | Pour les requêtes POST/PUT/PATCH |
| `X-Locale-Override` | `fr` | Recommandé pour forcer la langue |

### A.3. Authentication

Pour les routes protégées, utiliser l'authentification Bearer Token :
- Type : Bearer Token
- Token : `{{access_token}}`

---

## B. Tableau de Suivi des Tests

| ID | Endpoint | Scénario | HTTP Attendu | Éléments à vérifier | Priorité | Résultat Insomnia | Conclusion |
|----|----------|----------|--------------|-------------------|----------|-------------------|------------|
| TEST-001 | GET /locale | Contexte initial | 200 | language.code, country, country_selection_required | P1 | Non exécuté | En attente |
| TEST-002 | GET /countries/active | Liste pays actifs | 200 | countries array, country_selection_required | P1 | Non exécuté | En attente |
| TEST-003 | POST /auth/register/send-code | Envoi code inscription | 200 | message, email, expires_in, resend_available_in | P1 | Non exécuté | En attente |
| TEST-004 | POST /auth/register/verify-code | Vérification code | 200 | verification_token, expires_in | P1 | Non exécuté | En attente |
| TEST-005 | POST /auth/register/complete | Finalisation inscription | 201 | access_token, refresh_token, user | P1 | Non exécuté | En attente |
| TEST-006 | POST /auth/login | Login étape 1 | 200 | challenge_token, email, expires_in | P1 | Non exécuté | En attente |
| TEST-007 | POST /auth/login/verify-code | Login étape 2 | 200 | access_token, refresh_token, user | P1 | Non exécuté | En attente |
| TEST-008 | POST /auth/refresh | Refresh token | 200 | access_token, refresh_token, user | P1 | Non exécuté | En attente |
| TEST-009 | GET /auth/me | Profil utilisateur | 200 | user object complet | P2 | Non exécuté | En attente |
| TEST-010 | PUT /auth/profile | Modification profil | 200 | message, user | P2 | Non exécuté | En attente |
| TEST-011 | POST /auth/profile/verify-email | Vérification email | 200 | message, user | P2 | Non exécuté | En attente |
| TEST-012 | PUT /auth/password | Changement mot de passe | 200 | message, other_sessions_revoked | P2 | Non exécuté | En attente |
| TEST-013 | GET /auth/devices | Liste appareils | 200 | devices array, family_id | P2 | Non exécuté | En attente |
| TEST-014 | DELETE /auth/devices/{id} | Déconnexion appareil | 200 | message, current_device_revoked | P2 | Non exécuté | En attente |
| TEST-015 | DELETE /auth/devices | Déconnexion autres | 200 | message, revoked_count | P2 | Non exécuté | En attente |
| TEST-016 | POST /auth/logout | Déconnexion | 200 | message | P2 | Non exécuté | En attente |
| TEST-017 | PATCH /locale | Mise à jour locale | 200 | message, locale, user | P2 | Non exécuté | En attente |
| TEST-018 | DELETE /auth/account | Suppression compte | 200 | message | P2 | Non exécuté | En attente |
| TEST-019 | POST /auth/forgot-password/send-code | Envoi code forgot | 200 | message, email, expires_in | P3 | Non exécuté | En attente |
| TEST-020 | POST /auth/forgot-password/verify-code | Vérification forgot | 200 | reset_token, expires_in | P3 | Non exécuté | En attente |
| TEST-021 | POST /auth/forgot-password/reset | Réinitialisation mot de passe | 200 | message | P3 | Non exécuté | En attente |
| TEST-022 | POST /auth/verify-pending-email | Email pending guest | 200 | message | P3 | Non exécuté | En attente |
| TEST-023 | POST /auth/register/resend-code | Renvoi code register | 200 | message, expires_in | P3 | Non exécuté | En attente |
| TEST-024 | POST /auth/login/resend-code | Renvoi code login | 200 | message, expires_in | P3 | Non exécuté | En attente |
| TEST-025 | POST /auth/forgot-password/resend-code | Renvoi code forgot | 200 | message, expires_in | P3 | Non exécuté | En attente |
| TEST-026 | GET /auth/me (sans auth) | Sans authentification | 401 | code: unauthenticated | P4 | Non exécuté | En attente |
| TEST-027 | GET /auth/me (token invalide) | Token invalide | 401 | code: unauthenticated | P4 | Non exécuté | En attente |
| TEST-028 | POST /auth/register/send-code (sans email) | Champ manquant | 422 | code: validation_failed, errors.email | P4 | Non exécuté | En attente |
| TEST-029 | POST /auth/login (email invalide) | Type incorrect | 422 | code: validation_failed | P4 | Non exécuté | En attente |
| TEST-030 | POST /auth/login (rate limit) | Rate limiting | 429 | code: too_many_requests, retry_after | P4 | Non exécuté | En attente |
| TEST-031 | DELETE /auth/devices/{id} (inexistant) | Device non trouvé | 404 | code: device_not_found | P4 | Non exécuté | En attente |
| TEST-032 | POST /auth/login/verify-code (code expiré) | Code OTP expiré | 422 | code: otp_expired | P4 | Non exécuté | En attente |

---

## C. Détails des Requêtes de Test

### C.1. TEST-001 : GET /locale - Contexte initial

**Objectif :** Obtenir le contexte de langue et pays initial

**Méthode :** GET  
**URL :** `{{locale_url}}`

**Headers :**
```
Accept: application/json
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : language, country, country_selection_required, preferences, supported_languages, countries

**Résultat OpenAPI :**
- HTTP 200
- Schéma : LocaleContext

**Éléments à contrôler :**
- Présence de language.code
- Présence de country (ou null)
- Valeur de country_selection_required
- Structure de supported_languages
- Structure de countries

**Critère de réussite :** La réponse correspond au schéma LocaleContext

**Écart potentiel :** Structure du champ country (null vs objet)

---

### C.2. TEST-002 : GET /countries/active - Liste pays actifs

**Objectif :** Obtenir la liste des pays actifs disponibles

**Méthode :** GET  
**URL :** `{{countries_url}}`

**Headers :**
```
Accept: application/json
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : country_selection_required, countries array

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { country_selection_required: boolean, countries: Country[] }

**Éléments à contrôler :**
- Présence de country_selection_required
- Structure de chaque pays (id, code, name, native_name, phone_code, language)
- Langue associée à chaque pays

**Critère de réussite :** La réponse correspond au schéma documenté

**Écart potentiel :** Aucun rate limiter documenté

---

### C.3. TEST-003 : POST /auth/register/send-code - Envoi code inscription

**Objectif :** Initier le flux d'inscription en envoyant un code OTP

**Méthode :** POST  
**URL :** `{{auth_url}}/register/send-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "{{test_email}}"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, email, expires_in (600), resend_available_in (60)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, email, expires_in, resend_available_in }

**Éléments à contrôler :**
- Valeur de expires_in (devrait être 600 secondes)
- Valeur de resend_available_in (devrait être 60 secondes)
- Message de succès

**Critère de réussite :** Réponse 200 avec champs attendus

**Écart potentiel :** Valeurs d'expiration différentes

**Note :** Récupérer le code OTP dans les logs Laravel ou la table verification_codes

---

### C.4. TEST-004 : POST /auth/register/verify-code - Vérification code

**Objectif :** Vérifier le code OTP et obtenir un verification_token

**Méthode :** POST  
**URL :** `{{auth_url}}/register/verify-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "{{test_email}}",
  "code": "123456"
}
```

**Remplacer 123456 par le code réel récupéré dans les logs**

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, verification_token, email, expires_in (900)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, verification_token, email, expires_in }

**Éléments à contrôler :**
- Présence de verification_token
- Valeur de expires_in (devrait être 900 secondes = 15 min)
- Message de confirmation

**Critère de réussite :** Réponse 200 avec verification_token

**Écart potentiel :** Message incorrect ("code envoyé" au lieu de "code vérifié")

**Note :** Stocker la valeur de verification_token dans la variable Insomnia

---

### C.5. TEST-005 : POST /auth/register/complete - Finalisation inscription

**Objectif :** Créer le compte utilisateur avec le verification_token

**Méthode :** POST  
**URL :** `{{auth_url}}/register/complete`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "verification_token": "{{verification_token}}",
  "first_name": "Test",
  "last_name": "User",
  "password": "{{test_password}}",
  "password_confirmation": "{{test_password}}",
  "country_id": 1,
  "language_id": 1,
  "device_name": "Insomnia Test Device"
}
```

**Remplacer country_id et language_id selon les données de votre base**

**Résultat attendu (code source) :**
- HTTP 201
- JSON avec : message, access_token, refresh_token, token_type, expires_in, refresh_expires_in, user

**Résultat OpenAPI :**
- HTTP 201
- Schéma : AuthTokenResponse

**Éléments à contrôler :**
- Présence de access_token
- Présence de refresh_token
- Valeur de expires_in (devrait être 86400 secondes = 24h)
- Valeur de refresh_expires_in (devrait être 2592000 secondes = 30 jours)
- Structure de user (id, first_name, last_name, email, etc.)

**Critère de réussite :** Réponse 201 avec tokens complets

**Écart potentiel :** country_id requis même si un seul pays actif

**Note :** Stocker access_token et refresh_token dans les variables Insomnia

---

### C.6. TEST-006 : POST /auth/login - Login étape 1

**Objectif :** Vérifier les identifiants et envoyer un code 2FA

**Méthode :** POST  
**URL :** `{{auth_url}}/login`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "{{test_email}}",
  "password": "{{test_password}}"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, challenge_token, email, expires_in (600), resend_available_in (60)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, challenge_token, email, expires_in, resend_available_in }

**Éléments à contrôler :**
- Présence de challenge_token
- Valeur de expires_in (600 secondes)
- Message indiquant de saisir le code

**Critère de réussite :** Réponse 200 avec challenge_token

**Écart potentiel :** Aucun

**Note :** Stocker challenge_token dans la variable Insomnia, récupérer le code OTP

---

### C.7. TEST-007 : POST /auth/login/verify-code - Login étape 2

**Objectif :** Valider le code 2FA et obtenir les tokens

**Méthode :** POST  
**URL :** `{{auth_url}}/login/verify-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "challenge_token": "{{challenge_token}}",
  "code": "123456",
  "device_name": "Insomnia Login Test"
}
```

**Remplacer 123456 par le code réel**

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, access_token, refresh_token, token_type, expires_in, refresh_expires_in, user

**Résultat OpenAPI :**
- HTTP 200
- Schéma : AuthTokenResponse

**Éléments à contrôler :**
- Présence de access_token et refresh_token
- Valeurs d'expiration correctes
- Structure de user

**Critère de réussite :** Réponse 200 avec tokens

**Écart potentiel :** Aucun

**Note :** Stocker access_token et refresh_token dans les variables Insomnia

---

### C.8. TEST-008 : POST /auth/refresh - Refresh token

**Objectif :** Rafraîchir l'access token avec le refresh token

**Méthode :** POST  
**URL :** `{{auth_url}}/refresh`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "refresh_token": "{{refresh_token}}"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, access_token (nouveau), refresh_token (nouveau), token_type, expires_in, refresh_expires_in, user

**Résultat OpenAPI :**
- HTTP 200
- Schéma : AuthTokenResponse

**Éléments à contrôler :**
- Nouvel access_token différent de l'ancien
- Nouveau refresh_token (rotation)
- Valeurs d'expiration correctes

**Critère de réussite :** Réponse 200 avec nouveaux tokens

**Écart potentiel :** Aucun

**Note :** Mettre à jour access_token et refresh_token dans les variables Insomnia

---

### C.9. TEST-009 : GET /auth/me - Profil utilisateur

**Objectif :** Récupérer le profil de l'utilisateur authentifié

**Méthode :** GET  
**URL :** `{{auth_url}}/me`

**Headers :**
```
Accept: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : { user: User }

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { user: User }

**Éléments à contrôler :**
- Structure complète de user
- Présence de language et country
- Présence de pending_email (null si pas de changement en cours)

**Critère de réussite :** Réponse 200 avec user complet

**Écart potentiel :** Champs manquants dans user

---

### C.10. TEST-010 : PUT /auth/profile - Modification profil

**Objectif :** Modifier le nom et prénom de l'utilisateur

**Méthode :** PUT  
**URL :** `{{auth_url}}/profile`

**Headers :**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :**
```json
{
  "first_name": "Updated",
  "last_name": "Name"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, user

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, user }

**Éléments à contrôler :**
- first_name et last_name mis à jour
- Champs country_id et language_id NE DOIVENT PAS être acceptés

**Critère de réussite :** Réponse 200 avec profil mis à jour

**Écart potentiel :** country_id/language_id encore acceptés (changement cassant non appliqué)

---

### C.11. TEST-011 : POST /auth/profile/verify-email - Vérification email connecté

**Objectif :** Valider un changement d'email (scénario connecté)

**Note :** Ce test nécessite d'abord de modifier l'email via PUT /auth/profile

**Méthode :** POST  
**URL :** `{{auth_url}}/profile/verify-email`

**Headers :**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :**
```json
{
  "code": "123456"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, user

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, user }

**Éléments à contrôler :**
- Email mis à jour dans user
- pending_email à null

**Critère de réussite :** Réponse 200 avec email changé

**Écart potentiel :** Aucun

---

### C.12. TEST-012 : PUT /auth/password - Changement mot de passe

**Objectif :** Changer le mot de passe de l'utilisateur

**Méthode :** PUT  
**URL :** `{{auth_url}}/password`

**Headers :**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :**
```json
{
  "current_password": "{{test_password}}",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, other_sessions_revoked

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, other_sessions_revoked }

**Éléments à contrôler :**
- Présence de other_sessions_revoked
- Nombre de sessions révoquées

**Critère de réussite :** Réponse 200 avec other_sessions_revoked

**Écart potentiel :** other_sessions_revoked manquant

**Note :** Mettre à jour test_password avec le nouveau mot de passe

---

### C.13. TEST-013 : GET /auth/devices - Liste appareils

**Objectif :** Lister les appareils connectés

**Méthode :** GET  
**URL :** `{{auth_url}}/devices`

**Headers :**
```
Accept: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : { devices: Device[] }

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { devices: Device[] }

**Éléments à contrôler :**
- Structure de Device (id, name, is_current, last_used_at, created_at)
- id est un UUID (family_id)
- is_current indique l'appareil courant

**Critère de réussite :** Réponse 200 avec liste d'appareils

**Écart potentiel :** id n'est pas un UUID stable

---

### C.14. TEST-014 : DELETE /auth/devices/{id} - Déconnexion appareil

**Objectif :** Déconnecter un appareil spécifique

**Méthode :** DELETE  
**URL :** `{{auth_url}}/devices/{{device_id}}`

**Remplacer {{device_id}} par un UUID récupéré dans TEST-013**

**Headers :**
```
Accept: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, current_device_revoked (true si appareil courant)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, current_device_revoked: boolean | null }

**Éléments à contrôler :**
- Message de succès
- current_device_revoked présent si appareil courant révoqué

**Critère de réussite :** Réponse 200

**Écart potentiel :** current_device_revoked manquant

---

### C.15. TEST-015 : DELETE /auth/devices - Déconnexion autres appareils

**Objectif :** Déconnecter tous les appareils sauf le courant

**Méthode :** DELETE  
**URL :** `{{auth_url}}/devices`

**Headers :**
```
Accept: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, revoked_count

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, revoked_count }

**Éléments à contrôler :**
- Présence de revoked_count
- Nombre d'appareils révoqués

**Critère de réussite :** Réponse 200 avec revoked_count

**Écart potentiel :** revoked_count manquant

---

### C.16. TEST-016 : POST /auth/logout - Déconnexion

**Objectif :** Déconnecter la session courante

**Méthode :** POST  
**URL :** `{{auth_url}}/logout`

**Headers :**
```
Accept: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message }

**Éléments à contrôler :**
- Message de succès
- Token révoqué (vérifier avec TEST-009 ensuite)

**Critère de réussite :** Réponse 200

**Écart potentiel :** Aucun

**Note :** Après ce test, access_token est invalide

---

### C.17. TEST-017 : PATCH /locale - Mise à jour locale

**Objectif :** Mettre à jour les préférences de langue/pays

**Note :** Nécessite de se reconnecter d'abord (TEST-006, TEST-007)

**Méthode :** PATCH  
**URL :** `{{locale_url}}`

**Headers :**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :**
```json
{
  "language_id": 2,
  "country_id": 1
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, locale, user

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, locale: LocaleContext, user: User }

**Éléments à contrôler :**
- Locale mis à jour dans response
- Preferences mis à jour dans user
- user.language_id et user.country_id mis à jour

**Critère de réussite :** Réponse 200 avec locale modifiée

**Écart potentiel :** reset_to_auto combiné avec country_id/language_id accepté

---

### C.18. TEST-018 : DELETE /auth/account - Suppression compte

**Objectif :** Supprimer le compte utilisateur

**Note :** Test destructif - utiliser un compte de test dédié

**Méthode :** DELETE  
**URL :** `{{auth_url}}/account`

**Headers :**
```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :**
```json
{
  "password": "{{test_password}}"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message }

**Éléments à contrôler :**
- Message de succès
- Compte supprimé (soft delete)

**Critère de réussite :** Réponse 200

**Écart potentiel :** Mot de passe non requis

**Note :** Après ce test, tous les tokens sont invalides

---

### C.19. TEST-019 : POST /auth/forgot-password/send-code - Envoi code forgot

**Objectif :** Demander un code de réinitialisation de mot de passe

**Méthode :** POST  
**URL :** `{{auth_url}}/forgot-password/send-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "{{test_email}}"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, email, expires_in (600), resend_available_in (60)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, email, expires_in, resend_available_in }

**Éléments à contrôler :**
- Même réponse que le compte existe ou non (anti-énumération)
- Valeurs d'expiration correctes

**Critère de réussite :** Réponse 200 même si compte inexistant

**Écart potentiel :** Réponse différente selon existence du compte

**Note :** Récupérer le code OTP dans les logs

---

### C.20. TEST-020 : POST /auth/forgot-password/verify-code - Vérification forgot

**Objectif :** Vérifier le code et obtenir un reset_token

**Méthode :** POST  
**URL :** `{{auth_url}}/forgot-password/verify-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "{{test_email}}",
  "code": "123456"
}
```

**Remplacer 123456 par le code réel**

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, reset_token, email, expires_in (900)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, reset_token, email, expires_in }

**Éléments à contrôler :**
- Présence de reset_token
- Valeur de expires_in (900 secondes)

**Critère de réussite :** Réponse 200 avec reset_token

**Écart potentiel :** Aucun

**Note :** Stocker reset_token dans la variable Insomnia

---

### C.21. TEST-021 : POST /auth/forgot-password/reset - Réinitialisation mot de passe

**Objectif :** Réinitialiser le mot de passe avec le reset_token

**Méthode :** POST  
**URL :** `{{auth_url}}/forgot-password/reset`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "reset_token": "{{reset_token}}",
  "password": "NewPassword456!",
  "password_confirmation": "NewPassword456!"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message }

**Éléments à contrôler :**
- Message de succès
- Mot de passe changé

**Critère de réussite :** Réponse 200

**Écart potentiel :** Token nommé "token" au lieu de "reset_token"

**Note :** Mettre à jour test_password avec le nouveau mot de passe

---

### C.22. TEST-022 : POST /auth/verify-pending-email - Email pending guest

**Objectif :** Valider un changement d'email en étant déconnecté

**Note :** Nécessite d'avoir initié un changement d'email puis déconnecté

**Méthode :** POST  
**URL :** `{{auth_url}}/verify-pending-email`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "new.email@example.com",
  "code": "123456"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message }

**Éléments à contrôler :**
- Message de succès
- Email changé

**Critère de réussite :** Réponse 200

**Écart potentiel :** Aucun

---

### C.23. TEST-025 : POST /auth/register/resend-code - Renvoi code register

**Objectif :** Renvoyer un code d'inscription après cooldown

**Méthode :** POST  
**URL :** `{{auth_url}}/register/resend-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "{{test_email}}"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, email, expires_in (600), resend_available_in (60)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, email, expires_in, resend_available_in }

**Éléments à contrôler :**
- Nouveau code envoyé
- Valeur de resend_available_in

**Critère de réussite :** Réponse 200 après cooldown de 60 secondes

**Écart potentiel :** Cooldown non respecté

---

### C.24. TEST-024 : POST /auth/login/resend-code - Renvoi code login

**Objectif :** Renvoyer un code de login après cooldown

**Méthode :** POST  
**URL :** `{{auth_url}}/login/resend-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "challenge_token": "{{challenge_token}}"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, challenge_token, expires_in (600), resend_available_in (60)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, challenge_token, expires_in, resend_available_in }

**Éléments à contrôler :**
- Nouveau challenge_token
- Valeur de resend_available_in

**Critère de réussite :** Réponse 200 après cooldown

**Écart potentiel :** Email accepté comme fallback (ne devrait pas l'être)

---

### C.25. TEST-025 : POST /auth/forgot-password/resend-code - Renvoi code forgot

**Objectif :** Renvoyer un code de mot de passe oublié

**Méthode :** POST  
**URL :** `{{auth_url}}/forgot-password/resend-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "{{test_email}}"
}
```

**Résultat attendu (code source) :**
- HTTP 200
- JSON avec : message, email, expires_in (600), resend_available_in (60)

**Résultat OpenAPI :**
- HTTP 200
- Schéma : { message, email, expires_in, resend_available_in }

**Éléments à contrôler :**
- Nouveau code envoyé
- Valeur de resend_available_in

**Critère de réussite :** Réponse 200 après cooldown

**Écart potentiel :** Aucun

---

### C.26. TEST-026 : GET /auth/me (sans auth) - Sans authentification

**Objectif :** Vérifier le comportement sans authentification

**Méthode :** GET  
**URL :** `{{auth_url}}/me`

**Headers :**
```
Accept: application/json
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 401
- JSON avec : code: unauthenticated, message

**Résultat OpenAPI :**
- HTTP 401
- Schéma : ErrorResponse

**Éléments à contrôler :**
- Code HTTP 401
- Présence de code: unauthenticated

**Critère de réussite :** Réponse 401 avec code unauthenticated

**Écart potentiel :** Code différent ou message HTML

---

### C.27. TEST-027 : GET /auth/me (token invalide) - Token invalide

**Objectif :** Vérifier le comportement avec un token invalide

**Méthode :** GET  
**URL :** `{{auth_url}}/me`

**Headers :**
```
Accept: application/json
Authorization: Bearer invalid_token_12345
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 401
- JSON avec : code: unauthenticated, message

**Résultat OpenAPI :**
- HTTP 401
- Schéma : ErrorResponse

**Éléments à contrôler :**
- Code HTTP 401
- Présence de code: unauthenticated

**Critère de réussite :** Réponse 401 avec code unauthenticated

**Écart potentiel :** Code différent

---

### C.28. TEST-028 : POST /auth/register/send-code (sans email) - Champ manquant

**Objectif :** Vérifier la validation des champs obligatoires

**Méthode :** POST  
**URL :** `{{auth_url}}/register/send-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{}
```

**Résultat attendu (code source) :**
- HTTP 422
- JSON avec : code: validation_failed, errors: { email: [...] }

**Résultat OpenAPI :**
- HTTP 422
- Schéma : ValidationErrorResponse

**Éléments à contrôler :**
- Code HTTP 422
- Présence de code: validation_failed
- Présence de errors.email

**Critère de réussite :** Réponse 422 avec erreurs de validation

**Écart potentiel :** Code différent ou structure errors différente

---

### C.29. TEST-029 : POST /auth/login (email invalide) - Type incorrect

**Objectif :** Vérifier la validation des types de données

**Méthode :** POST  
**URL :** `{{auth_url}}/login`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": 12345,
  "password": "{{test_password}}"
}
```

**Résultat attendu (code source) :**
- HTTP 422
- JSON avec : code: validation_failed, errors: { email: [...] }

**Résultat OpenAPI :**
- HTTP 422
- Schéma : ValidationErrorResponse

**Éléments à contrôler :**
- Code HTTP 422
- Présence de code: validation_failed
- Message d'erreur sur le type

**Critère de réussite :** Réponse 422 avec erreur de type

**Écart potentiel :** Aucun

---

### C.30. TEST-030 : POST /auth/login (rate limit) - Rate limiting

**Objectif :** Vérifier le rate limiting

**Note :** Ce test nécessite d'envoyer plusieurs requêtes rapidement (plus de 5)

**Méthode :** POST  
**URL :** `{{auth_url}}/login`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "email": "{{test_email}}",
  "password": "wrong_password"
}
```

**Résultat attendu (code source) :**
- HTTP 429 (après 5 tentatives)
- Header Retry-After présent
- JSON avec : code: too_many_requests, retry_after

**Résultat OpenAPI :**
- HTTP 429
- Schéma : RateLimitErrorResponse

**Éléments à contrôler :**
- Code HTTP 429
- Présence de header Retry-After
- Présence de code: too_many_requests
- Présence de retry_after dans le corps

**Critère de réussite :** Réponse 429 avec Retry-After

**Écart potentiel :** Limiteur non appliqué ou valeur différente

---

### C.31. TEST-031 : DELETE /auth/devices/{id} (inexistant) - Device non trouvé

**Objectif :** Vérifier le comportement avec un device inexistant

**Méthode :** DELETE  
**URL :** `{{auth_url}}/devices/00000000-0000-0000-0000-000000000000`

**Headers :**
```
Accept: application/json
Authorization: Bearer {{access_token}}
X-Locale-Override: fr
```

**Corps :** Aucun

**Résultat attendu (code source) :**
- HTTP 404
- JSON avec : code: device_not_found, message

**Résultat OpenAPI :**
- HTTP 404
- Schéma : ErrorResponse (device_not_found)

**Éléments à contrôler :**
- Code HTTP 404
- Présence de code: device_not_found

**Critère de réussite :** Réponse 404 avec code device_not_found

**Écart potentiel :** Code 404 sans code spécifique

---

### C.32. TEST-032 : POST /auth/login/verify-code (code expiré) - Code OTP expiré

**Objectif :** Vérifier le comportement avec un code OTP expiré

**Note :** Attendre 10 minutes après l'envoi du code ou utiliser un code expiré

**Méthode :** POST  
**URL :** `{{auth_url}}/login/verify-code`

**Headers :**
```
Accept: application/json
Content-Type: application/json
X-Locale-Override: fr
```

**Corps :**
```json
{
  "challenge_token": "{{challenge_token}}",
  "code": "123456"
}
```

**Résultat attendu (code source) :**
- HTTP 422
- JSON avec : code: otp_expired, message

**Résultat OpenAPI :**
- HTTP 422
- Schéma : ValidationErrorResponse (otp_expired)

**Éléments à contrôler :**
- Code HTTP 422
- Présence de code: otp_expired
- Message approprié

**Critère de réussite :** Réponse 422 avec code otp_expired

**Écart potentiel :** Code otp_invalid au lieu de otp_expired

---

## D. Instructions pour l'Exécution

### D.1. Ordre recommandé

Exécuter les tests dans l'ordre suivant :

1. **Phase 1** : TEST-001 à TEST-008 (inscription et login)
2. **Phase 2** : TEST-009 à TEST-018 (routes protégées)
3. **Phase 3** : TEST-019 à TEST-025 (forgot password et resend)
4. **Phase 4** : TEST-026 à TEST-032 (cas d'erreur)

### D.2. Récupération des codes OTP

Pour les tests nécessitant un code OTP :

1. Exécuter la requête d'envoi de code
2. Ouvrir le fichier `storage/logs/laravel.log`
3. Chercher le code à 6 chiffres
4. Ou interroger la table `verification_codes` :
   ```sql
   SELECT code FROM verification_codes WHERE email = 'test.user@example.com' ORDER BY created_at DESC LIMIT 1;
   ```

### D.3. Gestion des variables Insomnia

Après chaque test qui génère un token :
- Copier la valeur du token
- Mettre à jour la variable Insomnia correspondante
- Les requêtes suivantes utiliseront automatiquement la nouvelle valeur

### D.4. Réinitialisation entre tests

Pour réinitialiser la base de données entre les scénarios :
```bash
php artisan migrate:fresh --seed
```

### D.5. Enregistrement des résultats

Pour chaque test exécuté :
1. Noter le code HTTP obtenu
2. Copier le corps de la réponse JSON
3. Comparer avec le résultat attendu
4. Mettre à jour le tableau de suivi dans `docs/api-audit/03-suivi-tests.md`

---

## E. Points à Confirmer par Tests HTTP

### E.1. Validations de champs

- country_id requis si >1 pays actif dans register/complete
- language_id nullable vs required
- device_name optionnel dans login/verify-code
- country_id/language_id retirés de PUT /auth/profile
- reset_to_auto exclusif avec country_id/language_id

### E.2. Réponses d'erreur

- Messages exacts de otp_expired, otp_max_attempts, refresh_token_expired
- Structure du champ errors dans les réponses 422
- Présence de other_sessions_revoked dans PUT /password
- Présence de current_device_revoked dans DELETE /devices/{id}

### E.3. Authentification

- Comportement exact sur 401 (code unauthenticated)
- Comportement avec token invalide/expiré
- Révocation effective des tokens après logout

### E.4. Rate Limiting

- Valeurs réelles des limiteurs (60/min, 5/min, etc.)
- Présence du header Retry-After
- Comportement après dépassement

### E.5. Structure des réponses

- Champs nullable vs required dans les réponses
- Structure de User (tous les champs présents)
- Structure de Device (id est bien un UUID)
- Structure de LocaleContext

---

**Fin du plan de tests Insomnia**
