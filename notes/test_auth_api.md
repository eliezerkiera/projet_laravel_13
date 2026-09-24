Voici un **guide de test complet** avec Insomnia, dans le bon ordre.

---

### Préparation

1. Assure-toi que ton serveur tourne :
```bash
php artisan serve
```

2. Base URL à utiliser dans Insomnia :
```
http://127.0.0.1:8000/api/v1
```

3. Dans Insomnia, crée un **Environment** avec ces variables :
```json
{
  "base_url": "http://127.0.0.1:8000/api/v1",
  "access_token": "",
  "refresh_token": ""
}
```

---

### Ordre de test recommandé

### 1. Register (Inscription)

- **Method** : `POST`
- **URL** : `{{ base_url }}/auth/register`
- **Body** (JSON) :

```json
{
  "first_name": "Jean",
  "last_name": "Dupont",
  "email": "jean.dupont@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "language_id": 1,
  "country_id": 1,
  "device_name": "Insomnia Test"
}
```

**Résultat attendu** : `201` + `access_token` + `refresh_token`

→ Copie les deux tokens dans ton environment Insomnia.

---

### 2. Me (Profil connecté)

- **Method** : `GET`
- **URL** : `{{ base_url }}/auth/me`
- **Header** :
```
Authorization: Bearer {{ access_token }}
```

**Résultat attendu** : données de l’utilisateur.

---

### 3. Refresh Token

- **Method** : `POST`
- **URL** : `{{ base_url }}/auth/refresh`
- **Header** :
```
Authorization: Bearer {{ refresh_token }}
```
- **Body** :
```json
{
  "device_name": "Insomnia Test"
}
```

**Résultat attendu** : nouvelle paire de tokens.

→ Mets à jour `access_token` et `refresh_token` dans l’environment.

---

### 4. Login

- **Method** : `POST`
- **URL** : `{{ base_url }}/auth/login`
- **Body** :
```json
{
  "email": "jean.dupont@example.com",
  "password": "Password123!",
  "device_name": "Insomnia Test"
}
```

---

### 5. Liste des appareils

- **Method** : `GET`
- **URL** : `{{ base_url }}/auth/devices`
- **Header** : `Authorization: Bearer {{ access_token }}`

---

### 6. Mise à jour du profil

- **Method** : `PUT`
- **URL** : `{{ base_url }}/auth/profile`
- **Header** : `Authorization: Bearer {{ access_token }}`
- **Body** :
```json
{
  "first_name": "Jean-Pierre",
  "last_name": "Dupont"
}
```

---

### 7. Changer le mot de passe

- **Method** : `PUT`
- **URL** : `{{ base_url }}/auth/password`
- **Header** : `Authorization: Bearer {{ access_token }}`
- **Body** :
```json
{
  "current_password": "Password123!",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

---

### 8. Forgot Password

- **Method** : `POST`
- **URL** : `{{ base_url }}/auth/forgot-password`
- **Body** :
```json
{
  "email": "jean.dupont@example.com"
}
```

→ Regarde le lien dans `storage/logs/laravel.log` (si tu es en `MAIL_MAILER=log`).

---

### 9. Logout

- **Method** : `POST`
- **URL** : `{{ base_url }}/auth/logout`
- **Header** : `Authorization: Bearer {{ access_token }}`

---

### 10. Suppression du compte

- **Method** : `DELETE`
- **URL** : `{{ base_url }}/auth/account`
- **Header** : `Authorization: Bearer {{ access_token }}`
- **Body** :
```json
{
  "password": "NewPassword123!"
}
```

---

### Astuce Insomnia

Tu peux créer un **Folder** nommé `Auth` et y mettre toutes ces requêtes dans l’ordre.  
Utilise les variables d’environnement pour éviter de copier-coller les tokens à chaque fois.

---

Tu veux que je te donne aussi les cas d’erreur à tester (mauvais mot de passe, token expiré, rate limit, etc.) ?