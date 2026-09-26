# Guide de Test de l'API v2 avec Refresh Token

Ce guide explique comment tester le système de refresh token de l'API v2 avec **Insomnia** (ou Postman).

---

## 1. Vue d'ensemble du système de Refresh Token

Le système de refresh token fonctionne comme suit :

- **Access Token** : Valide 1 jour (1440 minutes). Utilisé pour authentifier les requêtes API.
- **Refresh Token** : Valide 1 mois. Utilisé pour obtenir un nouvel access token sans se reconnecter.
- **Rotation des tokens** : Chaque fois qu'un refresh token est utilisé, un nouveau refresh token est généré et l'ancien est révoqué (meilleure sécurité).
- **Révocation** : Les refresh tokens sont révoqués lors de la déconnexion ou de la suppression d'appareils.

---

## 2. Préparation dans Insomnia

### A. Mettre à jour l'environnement Insomnia

Ajoutez la variable `refresh_token` à votre environnement existant :

```json
{
  "base_url": "http://127.0.0.1:8000/api/v2/auth",
  "access_token": "",
  "refresh_token": "",
  "verification_token": "",
  "challenge_token": "",
  "reset_token": ""
}
```

---

## 3. Flux d'authentification avec Refresh Token

### Étape 1 : Connexion standard (avec retour du refresh token)

Effectuez une connexion normale via les endpoints existants. La réponse inclut maintenant un `refresh_token` en plus de l'`access_token`.

#### 1. Connexion - Étape 1 : Identifiants
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/login`
- **Body (JSON)** :
  ```json
  {
    "email": "utilisateur.test@example.com",
    "password": "Password123!"
  }
  ```
- **Résultat attendu** : `200 OK` avec `challenge_token`

#### 2. Connexion - Étape 2 : Vérification du code 2FA
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/login/verify-code`
- **Body (JSON)** :
  ```json
  {
    "challenge_token": "{{ challenge_token }}",
    "code": "123456",
    "device_name": "iPhone 15 Test"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Logged in successfully.",
    "access_token": "1|abcdef...",
    "refresh_token": "xyz789...", // NOUVEAU : jeton de rafraîchissement
    "token_type": "Bearer",
    "user": { ... }
  }
  ```
- **Action** : Copiez les valeurs de `access_token` et `refresh_token` dans vos variables d'environnement.

---

### Étape 2 : Utilisation de l'access token

Utilisez l'access token normalement pour les requêtes authentifiées.

#### Exemple : Récupérer le profil
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/me`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Accept: application/json
  ```

---

### Étape 3 : Rafraîchir l'access token

Lorsque l'access token expire (après 1 jour), utilisez le refresh token pour en obtenir un nouveau sans se reconnecter.

#### Endpoint de refresh
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/refresh`
- **Headers** :
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "refresh_token": "{{ refresh_token }}"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Token refreshed successfully.",
    "access_token": "2|newtoken...", // NOUVEL access token
    "refresh_token": "newrefresh...", // NOUVEAU refresh token (rotation)
    "token_type": "Bearer",
    "user": { ... }
  }
  ```
- **Action** : Mettez à jour vos variables `access_token` et `refresh_token` avec les nouvelles valeurs.

---

## 4. Scénarios de test importants

### Scénario 1 : Refresh token valide
1. Connectez-vous et récupérez les tokens
2. Attendez que l'access token expire (ou testez immédiatement)
3. Utilisez le refresh token pour obtenir un nouvel access token
4. Vérifiez que le nouveau refresh token est différent de l'ancien (rotation)

### Scénario 2 : Refresh token expiré
1. Modifiez manuellement un refresh token en base de données pour qu'il soit expiré (`expires_at` dans le passé)
2. Tentez de l'utiliser avec l'endpoint `/refresh`
3. **Résultat attendu** : `422 Unprocessable Content` avec le message "The refresh token has expired. Please log in again."

### Scénario 3 : Refresh token révoqué
1. Connectez-vous
2. Déconnectez-vous via `POST {{ base_url }}/logout`
3. Tentez d'utiliser le refresh token avec l'endpoint `/refresh`
4. **Résultat attendu** : `422 Unprocessable Content` avec le message "The refresh token is invalid."

### Scénario 4 : Refresh token invalide
1. Utilisez une chaîne aléatoire comme refresh token
2. Tentez de l'utiliser avec l'endpoint `/refresh`
3. **Résultat attendu** : `422 Unprocessable Content` avec le message "The refresh token is invalid."

### Scénario 5 : Rotation des refresh tokens
1. Connectez-vous et récupérez le premier refresh token (`rt1`)
2. Utilisez `rt1` pour rafraîchir → obtenez `rt2`
3. Tentez d'utiliser `rt1` à nouveau
4. **Résultat attendu** : `422 Unprocessable Content` (le premier token a été révoqué)

---

## 5. Inscription avec Refresh Token

L'inscription suit le même schéma que la connexion : le refresh token est retourné avec l'access_token après la finalisation de l'inscription.

#### Inscription - Étape 3 : Finalisation
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/register/complete`
- **Body (JSON)** :
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
- **Résultat attendu** : `201 Created` avec `access_token` et `refresh_token`

---

## 6. Gestion des appareils et Refresh Tokens

### Déconnexion d'un appareil spécifique
Lorsque vous déconnectez un appareil via `DELETE {{ base_url }}/devices/{id}`, le refresh token correspondant à cet appareil est également révoqué.

### Déconnexion de tous les autres appareils
Lorsque vous déconnectez tous les autres appareils via `DELETE {{ base_url }}/devices`, leurs refresh tokens sont révoqués, mais le refresh token de l'appareil actuel reste valide.

### Déconnexion de l'appareil actuel
Lorsque vous vous déconnectez via `POST {{ base_url }}/logout`, le refresh token de l'appareil actuel est révoqué.

---

## 7. Configuration des durées de validité

Les durées de validité sont configurées comme suit :

- **Access Token** : 1 jour (1440 minutes) - configuré dans `config/sanctum.php`
- **Refresh Token** : 1 mois - configuré dans `RefreshTokenController.php` (`now()->addMonth()`)

Pour modifier ces durées :
- Access token : Modifiez la valeur `'expiration'` dans `config/sanctum.php`
- Refresh token : Modifiez `now()->addMonth()` dans les contrôleurs (LoginController, RegisterController, RefreshTokenController)

---

## 8. Points importants à retenir

1. **Stockez le refresh token en sécurité** : Il permet d'obtenir de nouveaux access tokens sans mot de passe.
2. **La rotation est automatique** : Chaque utilisation d'un refresh token génère un nouveau token.
3. **La révocation est immédiate** : Les refresh tokens sont révoqués lors de la déconnexion.
4. **L'endpoint `/refresh` est public** : Il ne nécessite pas d'authentification, seulement un refresh token valide.
5. **Utilisez toujours le dernier refresh token** : Les anciens refresh tokens sont révoqués après utilisation.

---

## 9. Exemple de workflow complet

```bash
# 1. Connexion
POST /api/v2/auth/login → challenge_token
POST /api/v2/auth/login/verify-code → access_token, refresh_token

# 2. Utilisation de l'API (tant que l'access token est valide)
GET /api/v2/auth/me (avec Authorization: Bearer access_token)

# 3. Après 1 jour (access token expiré)
POST /api/v2/auth/refresh (avec refresh_token) → new_access_token, new_refresh_token

# 4. Continuer à utiliser l'API
GET /api/v2/auth/me (avec Authorization: Bearer new_access_token)

# 5. Déconnexion
POST /api/v2/auth/logout → refresh_token révoqué
```

---

## 10. Dépannage

### Problème : "The refresh token has expired"
**Solution** : Le refresh token est valide 1 mois. S'il a expiré, l'utilisateur doit se reconnecter.

### Problème : "The refresh token is invalid"
**Solution** : Vérifiez que le refresh token n'a pas été révoqué (déconnexion) et qu'il correspond bien à un token valide en base de données.

### Problème : Access token fonctionne toujours après 1 jour
**Solution** : Vérifiez que la configuration `'expiration' => 1440` est bien présente dans `config/sanctum.php` et que le cache de configuration a été vidé (`php artisan config:clear`).
