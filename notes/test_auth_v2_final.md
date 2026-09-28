# Test final de l'API v2 d'authentification et de locale

Ce document est le guide de test final de l'API v2 du projet. Il couvre :

- l'inscription OTP,
- la connexion avec 2FA,
- la gestion du profil et des appareils,
- les refresh tokens,
- la liste des pays actifs,
- la gestion des préférences de langue/pays,
- les cas limites et validations attendues.

Le comportement final suit la logique actuelle de l'application :

- l'API est servie sous `/api/v2` ;
- la partie auth est sous `/api/v2/auth` ;
- l'endpoint des pays actifs est `/api/v2/countries/active` ;
- la détection du pays ne repose pas sur l'IP ;
- le pays est résolu selon les pays actifs et les préférences de l'utilisateur ;
- la langue est prioritairement déterminée par `X-Locale-Override`, puis `Accept-Language`, puis le profil utilisateur, puis le pays actif associé.

---

## 1. Préparation

### 1.1 Démarrer l'application

```bash
php artisan serve
```

### 1.2 Charger les données de référence

```bash
php artisan db:seed --class=DatabaseSeeder --no-interaction
```

Vérifiez que :

- les pays actifs existent bien dans la table `countries` ;
- au moins une langue `fr` et `en` est présente dans `languages` ;
- une liste de pays actifs est bien renvoyée via `/api/v2/countries/active` ;
- en environnement de test, les codes OTP sont visibles dans les logs Laravel (`MAIL_MAILER=log`) ou dans la table `verification_codes`.

### 1.3 Variables d'environnement Insomnia / Postman

Créez un environnement nommé `Laravel API v2` avec :

```json
{
  "base_url": "http://127.0.0.1:8000",
  "auth_url": "{{ base_url }}/api/v2/auth",
  "access_token": "",
  "refresh_token": "",
  "challenge_token": "",
  "verification_token": "",
  "reset_token": "",
  "email": "user.test@example.com",
  "password": "Password123!"
}
```

---

## 2. Endpoints disponibles

### 2.1 Authentification publique

- `POST {{ auth_url }}/register/send-code`
- `POST {{ auth_url }}/register/resend-code`
- `POST {{ auth_url }}/register/verify-code`
- `POST {{ auth_url }}/register/complete`
- `POST {{ auth_url }}/login`
- `POST {{ auth_url }}/login/resend-code`
- `POST {{ auth_url }}/login/verify-code`
- `POST {{ auth_url }}/forgot-password/send-code`
- `POST {{ auth_url }}/forgot-password/resend-code`
- `POST {{ auth_url }}/forgot-password/verify-code`
- `POST {{ auth_url }}/forgot-password/reset`
- `POST {{ auth_url }}/refresh`
- `POST {{ auth_url }}/verify-pending-email`

### 2.2 Authentification requise

- `GET {{ auth_url }}/me`
- `PUT {{ auth_url }}/profile`
- `POST {{ auth_url }}/profile/verify-email`
- `PUT {{ auth_url }}/password`
- `DELETE {{ auth_url }}/account`
- `GET {{ auth_url }}/devices`
- `DELETE {{ auth_url }}/devices/{id}`
- `DELETE {{ auth_url }}/devices`
- `POST {{ auth_url }}/logout`
- `PATCH {{ auth_url }}/locale`

### 2.3 Pays actifs

- `GET {{ base_url }}/api/v2/countries/active`

---

## 3. Flux d'inscription complet

### Étape 1 : envoyer le code OTP

```http
POST {{ auth_url }}/register/send-code
Content-Type: application/json
Accept: application/json
```

```json
{
  "email": "user.test@example.com"
}
```

Résultat attendu : `200 OK`

```json
{
  "message": "A verification code has been sent to your email address.",
  "email": "user.test@example.com"
}
```

### Étape 2 : vérifier le code

```http
POST {{ auth_url }}/register/verify-code
Content-Type: application/json
Accept: application/json
```

```json
{
  "email": "user.test@example.com",
  "code": "123456"
}
```

Résultat attendu : `200 OK`

```json
{
  "message": "A verification code has been sent to your email address.",
  "verification_token": "<token_temporaire>",
  "email": "user.test@example.com"
}
```

Copiez la valeur de `verification_token` dans la variable `verification_token`.

### Étape 3 : finaliser l'inscription

```http
POST {{ auth_url }}/register/complete
Content-Type: application/json
Accept: application/json
```

```json
{
  "verification_token": "{{ verification_token }}",
  "first_name": "Jean",
  "last_name": "Dupont",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "country_id": 1,
  "language_id": 1,
  "device_name": "Insomnia Desktop"
}
```

Résultat attendu : `201 Created`

```json
{
  "message": "Your account has been registered successfully.",
  "access_token": "1|...",
  "refresh_token": "...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "first_name": "Jean",
    "last_name": "Dupont",
    "email": "user.test@example.com"
  }
}
```

Enregistrez :

- `access_token` dans `access_token` ;
- `refresh_token` dans `refresh_token`.

---

## 4. Connexion avec 2FA et refresh token

### Étape 1 : demander le code de connexion

```http
POST {{ auth_url }}/login
Content-Type: application/json
Accept: application/json
```

```json
{
  "email": "user.test@example.com",
  "password": "Password123!"
}
```

Résultat attendu : `200 OK`

```json
{
  "message": "Credentials verified. Please enter the verification code sent to your email.",
  "challenge_token": "<challenge_token>",
  "email": "user.test@example.com"
}
```

Copiez `challenge_token` dans `challenge_token`.

### Étape 2 : valider le code OTP

```http
POST {{ auth_url }}/login/verify-code
Content-Type: application/json
Accept: application/json
```

```json
{
  "challenge_token": "{{ challenge_token }}",
  "code": "123456",
  "device_name": "iPhone 15 Test"
}
```

Résultat attendu : `200 OK`

```json
{
  "message": "Logged in successfully.",
  "access_token": "1|...",
  "refresh_token": "...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "first_name": "Jean",
    "last_name": "Dupont",
    "email": "user.test@example.com"
  }
}
```

A chaque connexion, le refresh token est renvoyé. Cet accès est ensuite utilisé directement pour les requêtes authentifiées.

---

## 5. Utilisation du refresh token

### 5.1 Appel de profil avec access token

```http
GET {{ auth_url }}/me
Authorization: Bearer {{ access_token }}
Accept: application/json
```

Résultat attendu : `200 OK` avec les informations du profil et les relations `language` / `country` si chargées.

### 5.2 Rafraîchir l'access token

```http
POST {{ auth_url }}/refresh
Content-Type: application/json
Accept: application/json
```

```json
{
  "refresh_token": "{{ refresh_token }}"
}
```

Résultat attendu : `200 OK`

```json
{
  "message": "Token refreshed successfully.",
  "access_token": "2|...",
  "refresh_token": "<nouveau_refresh_token>",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "email": "user.test@example.com"
  }
}
```

Important :

- le `refresh_token` renvoyé par l'API est censé être remplacé immédiatement dans le client ;
- l'ancien refresh token est révoqué ;
- la rotation est obligatoire côté serveur.

### 5.3 Cas limites

Tester ces scénarios :

1. `refresh_token` invalide → `422` avec message `The refresh token is invalid.`
2. `refresh_token` expiré → `422` avec message `The refresh token has expired. Please log in again.`
3. `refresh_token` révoqué → `422` avec message `The refresh token is invalid.`
4. double utilisation du même refresh token → le second appel échoue car le serveur l'a déjà révoqué.

---

## 6. Gestion des pays actifs et locale

### 6.1 Récupérer la liste des pays actifs

```http
GET {{ base_url }}/api/v2/countries/active
Accept: application/json
```

Résultat attendu : `200 OK`

```json
{
  "country_selection_required": false,
  "countries": [
    {
      "id": 1,
      "code": "BF",
      "name": "Burkina Faso",
      "native_name": "Burkina Faso",
      "phone_code": "+226",
      "language": {
        "id": 1,
        "code": "fr",
        "name": "French"
      }
    }
  ]
}
```

Comportement final :

- si un seul pays est actif, `country_selection_required` est `false` ;
- si plusieurs pays sont actifs, `country_selection_required` est `true` ;
- le client doit afficher un sélecteur uniquement dans le second cas.

### 6.2 Tester la détection de langue

#### Sans header spécifique

```http
GET {{ base_url }}/api/v2/countries/active
Accept: application/json
```

Le serveur applique la langue selon la priorité suivante :

1. `X-Locale-Override` si présent ;
2. `Accept-Language` si présent ;
3. user preference si disponible ;
4. la langue du pays actif.

#### Avec `Accept-Language`

```http
GET {{ base_url }}/api/v2/countries/active
Accept: application/json
Accept-Language: en
```

La langue de la requête doit se comporter comme `en`.

```http
GET {{ base_url }}/api/v2/countries/active
Accept: application/json
Accept-Language: fr
```

La langue de la requête doit se comporter comme `fr`.

#### Avec override

```http
GET {{ base_url }}/api/v2/countries/active
Accept: application/json
Accept-Language: fr
X-Locale-Override: en
```

Le comportement attendu est que `X-Locale-Override` ait priorité sur `Accept-Language`.

---

## 7. Gestion des préférences de locale utilisateur

### 7.1 Vérifier le profil avec un utilisateur non configuré

```http
GET {{ auth_url }}/me
Authorization: Bearer {{ access_token }}
Accept: application/json
```

Le profil peut retourner :

- `country_id: null` ;
- `language_id: null` ;
- `country_source` et `language_source` en mode `auto` selon la logique applicative si aucun réglage manuel n'a été défini.

### 7.2 Définir manuellement la langue/pays

```http
PATCH {{ auth_url }}/locale
Authorization: Bearer {{ access_token }}
Content-Type: application/json
Accept: application/json
```

```json
{
  "country_id": 1,
  "language_id": 2
}
```

Résultat attendu : `200 OK`

```json
{
  "message": "Locale preferences updated successfully.",
  "user": {
    "id": 1,
    "country_id": 1,
    "language_id": 2,
    "country": {
      "id": 1,
      "code": "BF",
      "name": "Burkina Faso",
      "is_active": true
    },
    "language": {
      "id": 2,
      "code": "en",
      "name": "English"
    }
  }
}
```

Important :

- un `PATCH /locale` peut recevoir `country_id` et/ou `language_id` ;
- le serveur met à jour uniquement les champs fournis ;
- si `reset_to_auto` vaut `true`, il remet les préférences utilisateur en mode automatique.

### 7.3 Remettre les préférences en auto

```http
PATCH {{ auth_url }}/locale
Authorization: Bearer {{ access_token }}
Content-Type: application/json
Accept: application/json
```

```json
{
  "reset_to_auto": true
}
```

Résultat attendu : `200 OK`

```json
{
  "message": "Locale preferences reset to auto-detection.",
  "user": {
    "id": 1,
    "country_id": null,
    "language_id": null
  }
}
```

### 7.4 Validation des entrées

Tester ces cas :

#### Aucun champ fourni

```json
{}
```

Résultat attendu : `422 Unprocessable Content`

```json
{
  "message": "At least one of country_id or language_id must be provided."
}
```

#### Pays invalide

```json
{
  "country_id": 99999
}
```

Résultat attendu : `422` avec message `The selected country is invalid.`

#### Langue invalide

```json
{
  "language_id": 99999
}
```

Résultat attendu : `422` avec message `The selected language is invalid.`

---

## 8. Profil, mot de passe, email et appareils

### 8.1 Récupérer le profil

```http
GET {{ auth_url }}/me
Authorization: Bearer {{ access_token }}
Accept: application/json
```

### 8.2 Modifier le profil de base

```http
PUT {{ auth_url }}/profile
Authorization: Bearer {{ access_token }}
Content-Type: application/json
Accept: application/json
```

```json
{
  "first_name": "Jean-Pierre",
  "last_name": "Dupontel"
}
```

### 8.3 Changer l'email avec validation OTP

```http
PUT {{ auth_url }}/profile
Authorization: Bearer {{ access_token }}
Content-Type: application/json
Accept: application/json
```

```json
{
  "email": "new.email@example.com"
}
```

Résultat attendu : `200 OK`

```json
{
  "message": "Profile updated. A verification code has been sent to your new email address.",
  "pending_email": "new.email@example.com"
}
```

Valider ensuite :

```http
POST {{ auth_url }}/profile/verify-email
Authorization: Bearer {{ access_token }}
Content-Type: application/json
Accept: application/json
```

```json
{
  "code": "123456"
}
```

### 8.4 Changer le mot de passe

```http
PUT {{ auth_url }}/password
Authorization: Bearer {{ access_token }}
Content-Type: application/json
Accept: application/json
```

```json
{
  "current_password": "Password123!",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

### 8.5 Lister les appareils

```http
GET {{ auth_url }}/devices
Authorization: Bearer {{ access_token }}
Accept: application/json
```

### 8.6 Déconnecter un appareil spécifique

```http
DELETE {{ auth_url }}/devices/2
Authorization: Bearer {{ access_token }}
Accept: application/json
```

### 8.7 Déconnecter tous les autres appareils

```http
DELETE {{ auth_url }}/devices
Authorization: Bearer {{ access_token }}
Accept: application/json
```

### 8.8 Déconnexion courante

```http
POST {{ auth_url }}/logout
Authorization: Bearer {{ access_token }}
Accept: application/json
```

Résultat attendu : `200 OK` avec message de logout. La revocation du refresh token correspondant est appliquée.

---

## 9. Cas de test recommandés pour la validation finale

Vérifier que tous les comportements suivants passent :

- [ ] Inscription avec code OTP + création de compte
- [ ] Login + récupérations du `challenge_token`
- [ ] Validation du code OTP + génération de `access_token`
- [ ] Utilisation du `refresh_token` pour renouveler l'access token
- [ ] Rotation du refresh token à chaque rafraîchissement
- [ ] Rejet des tokens invalides/expirés/révoqués
- [ ] `GET /api/v2/countries/active` renvoie le bon `country_selection_required`
- [ ] `Accept-Language` et `X-Locale-Override` ont la priorité attendue
- [ ] Une préférence manuelle `country_id` / `language_id` reste persistée
- [ ] `reset_to_auto` remet les préférences en automatique
- [ ] `PATCH /api/v2/auth/locale` valide les entrées et rejette les champs invalides
- [ ] Le profil authentifié renvoie bien les relations pays/langue
- [ ] Les appareils connectés sont lister et déconnecter correctement
- [ ] Les tokens d'accès / refresh sont bien révoqués lors de la déconnexion

---

## 10. Checklist rapide d'API

### Collection Insomnia minimale

1. `GET {{ base_url }}/api/v2/countries/active`
2. `GET {{ base_url }}/api/v2/countries/active` avec `Accept-Language: en`
3. `GET {{ base_url }}/api/v2/countries/active` avec `Accept-Language: fr` + `X-Locale-Override: en`
4. `POST {{ auth_url }}/login`
5. `POST {{ auth_url }}/login/verify-code`
6. `GET {{ auth_url }}/me`
7. `POST {{ auth_url }}/refresh`
8. `PATCH {{ auth_url }}/locale`
9. `GET {{ auth_url }}/me`
10. `POST {{ auth_url }}/logout`

---

## 11. Points de vigilance

- Ne pas utiliser l'ancien `refresh_token` après sa rotation.
- Ne pas confondre `verification_token` et `challenge_token`.
- Ne pas envoyer de `X-Locale-Override` en tant que préférence persistée ; le header est seulement une priorité de requête.
- La logique actuelle de pays ne dépend pas de l'adresse IP ; le pays est résolu via les pays actifs ou la préférence utilisateur.
- Pour des tests réels, utiliser un même environnement et réinitialiser la base si nécessaire entre scénarios.

---

## 12. Résumé final

L'API finale couvre un flux robuste d'authentification OTP avec double facteur, des tokens sécurisés avec rotation de refresh tokens, une route publique de consultation des pays actifs, et une logique de locale centrée sur les préférences utilisateur et les headers HTTP. Ce guide permet de valider la totalité du système dans l'ordre le plus naturel de test, en couvrant la sécurité, la validation, la réactivité côté client et la cohérence de la configuration finale.
