# Guide de Test Complet de l'API v2 avec Insomnia

Ce guide pas à pas décrit comment tester l'ensemble du système d'authentification API v2 dans l'ordre recommandé avec **Insomnia** (ou Postman).

---

## 1. Préparation dans Insomnia

### A. Lancer le serveur Laravel
Dans votre terminal :
```bash
php artisan serve
```

### B. Créer un Environnement Insomnia
Dans Insomnia, créez un nouvel **Environment** (nommé par exemple `Laravel 13 API v2`) avec ces variables :

```json
{
  "base_url": "http://127.0.0.1:8000/api/v2/auth",
  "access_token": "",
  "verification_token": "",
  "challenge_token": "",
  "reset_token": ""
}
```

### C. Comment récupérer les codes OTP pendant vos tests ?
Selon votre configuration dans votre fichier `.env` :
- **Si `MAIL_MAILER=log`** : Ouvrez le fichier `storage/logs/laravel.log`, le code à 6 chiffres y sera affiché dans le corps de l'email envoyé.
- **Directement en base de données** : Exécutez la requête SQL :
  ```sql
  SELECT email, code, type, token, expires_at FROM verification_codes ORDER BY id DESC LIMIT 5;
  ```
  ou avec Artisan :
  ```bash
  php artisan db:table verification_codes
  ```

---

## 2. Déroulement des Tests par Fonctionnalité

---

### Phase 1 : Inscription Complète (Register avec OTP)

#### 1. Inscription - Étape 1 : Demande de code OTP
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/register/send-code`
- **Headers** :
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "email": "utilisateur.test@example.com"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "A verification code has been sent to your email address.",
    "email": "utilisateur.test@example.com"
  }
  ```

#### 2. Inscription - Étape 1b (Optionnel) : Renvoyer le code
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/register/resend-code`
- **Body (JSON)** :
  ```json
  {
    "email": "utilisateur.test@example.com"
  }
  ```
- **Note** : Si vous renvoyez le code dans les 60 secondes, l'API renvoie `422 Unprocessable Content` avec le message de cooldown.

#### 3. Inscription - Étape 2 : Vérification du code OTP
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/register/verify-code`
- **Body (JSON)** :
  *(Remplacez `123456` par le code à 6 chiffres récupéré dans vos logs ou en base)*
  ```json
  {
    "email": "utilisateur.test@example.com",
    "code": "123456"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "A verification code has been sent to your email address.",
    "verification_token": "a1b2c3d4e5f6... (jeton de 64 caractères)",
    "email": "utilisateur.test@example.com"
  }
  ```
- **Action** : Copiez la valeur de `verification_token` dans votre variable d'environnement `verification_token`.

#### 4. Inscription - Étape 3 : Finalisation du profil
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
- **Résultat attendu** : `201 Created`
  ```json
  {
    "message": "Your account has been registered successfully.",
    "access_token": "1|abcdef...",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "first_name": "Jean",
      "last_name": "Dupont",
      "email": "utilisateur.test@example.com"
    }
  }
  ```
- **Action** : Copiez la valeur de `access_token` dans votre variable d'environnement `access_token`.

---

### Phase 2 : Profil Connecté (Me)

#### 5. Récupérer le profil connecté
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/me`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Accept: application/json
  ```
- **Résultat attendu** : `200 OK` avec les informations du compte, de la langue et du pays.

---

### Phase 3 : Connexion avec Double Facteur (Login 2FA)

#### 6. Connexion - Étape 1 : Identifiants
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/login`
- **Body (JSON)** :
  ```json
  {
    "email": "utilisateur.test@example.com",
    "password": "Password123!"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Credentials verified. Please enter the verification code sent to your email.",
    "challenge_token": "xyz789... (jeton temporaire)",
    "email": "utilisateur.test@example.com"
  }
  ```
- **Action** : Copiez la valeur de `challenge_token` dans votre variable d'environnement `challenge_token`.

#### 7. Connexion - Étape 2 : Vérification du code 2FA
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
- **Résultat attendu** : `200 OK` avec un nouveau `access_token`.
- **Action** : Mettez à jour votre variable `access_token`.

---

### Phase 4 : Gestion des Appareils Connectés (Devices)

#### 8. Lister les appareils connectés
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/devices`
- **Headers** : `Authorization: Bearer {{ access_token }}`
- **Résultat attendu** : `200 OK` avec la liste des tokens, leur nom et l'attribut `is_current: true` pour le token actuel.

#### 9. Déconnecter un appareil spécifique
- **Méthode** : `DELETE`
- **URL** : `{{ base_url }}/devices/{id}` *(remplacez {id} par l'id d'un autre token)*
- **Headers** : `Authorization: Bearer {{ access_token }}`
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Device disconnected successfully."
  }
  ```

#### 10. Déconnecter tous les autres appareils
- **Méthode** : `DELETE`
- **URL** : `{{ base_url }}/devices`
- **Headers** : `Authorization: Bearer {{ access_token }}`
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "All other devices have been disconnected successfully."
  }
  ```

---

### Phase 5 : Mise à Jour du Profil & Changement d'Email avec OTP

#### 11. Modifier les informations de base (sans changer l'email)
- **Méthode** : `PUT`
- **URL** : `{{ base_url }}/profile`
- **Headers** : `Authorization: Bearer {{ access_token }}`
- **Body (JSON)** :
  ```json
  {
    "first_name": "Jean-Pierre",
    "last_name": "Dupontel"
  }
  ```
- **Résultat attendu** : `200 OK` (`"Profile updated successfully."`).

#### 12. Demande de changement d'email
- **Méthode** : `PUT`
- **URL** : `{{ base_url }}/profile`
- **Headers** : `Authorization: Bearer {{ access_token }}`
- **Body (JSON)** :
  ```json
  {
    "email": "nouvel.email@example.com"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Profile updated. A verification code has been sent to your new email address.",
    "pending_email": "nouvel.email@example.com"
  }
  ```
- *Remarque* : À cet instant, si vous tentez de vous connecter via `/login` avec l'ancien ou le nouveau mot de passe, l'API renvoie `403 Forbidden` en vous rappelant que vous devez valider votre nouvelle adresse.

#### 13. Confirmer le nouvel email avec le code OTP
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/profile/verify-email`
- **Headers** : `Authorization: Bearer {{ access_token }}`
- **Body (JSON)** :
  ```json
  {
    "code": "123456"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Email address verified and updated successfully."
  }
  ```

*(Note : Si l'utilisateur s'est déconnecté avant de valider, il peut utiliser l'endpoint public `POST {{ base_url }}/verify-pending-email` avec `{"email": "nouvel.email@example.com", "code": "123456"}`).*

---

### Phase 6 : Changement de Mot de Passe

#### 14. Modifier son mot de passe
- **Méthode** : `PUT`
- **URL** : `{{ base_url }}/password`
- **Headers** : `Authorization: Bearer {{ access_token }}`
- **Body (JSON)** :
  ```json
  {
    "current_password": "Password123!",
    "password": "NouveauPassword456!",
    "password_confirmation": "NouveauPassword456!"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Password changed successfully."
  }
  ```

---

### Phase 7 : Mot de Passe Oublié (Forgot Password)

#### 15. Demande de réinitialisation
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/forgot-password/send-code`
- **Body (JSON)** :
  ```json
  {
    "email": "nouvel.email@example.com"
  }
  ```
- **Résultat attendu** : `200 OK`

#### 16. Vérification du code de réinitialisation
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/forgot-password/verify-code`
- **Body (JSON)** :
  ```json
  {
    "email": "nouvel.email@example.com",
    "code": "123456"
  }
  ```
- **Résultat attendu** : `200 OK` avec `reset_token`.
- **Action** : Copiez la valeur de `reset_token` dans votre variable `reset_token`.

#### 17. Définir le nouveau mot de passe
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/forgot-password/reset`
- **Body (JSON)** :
  ```json
  {
    "token": "{{ reset_token }}",
    "password": "PasswordFinal789!",
    "password_confirmation": "PasswordFinal789!"
  }
  ```
- **Résultat attendu** : `200 OK` (`"Your password has been reset successfully."`).

---

### Phase 8 : Déconnexion & Suppression de Compte

#### 18. Déconnexion (Logout)
- **Méthode** : `POST`
- **URL** : `{{ base_url }}/logout`
- **Headers** : `Authorization: Bearer {{ access_token }}`
- **Résultat attendu** : `200 OK` (`"Logged out successfully."`).

#### 19. Suppression douce du compte (Soft Delete)
- **Méthode** : `DELETE`
- **URL** : `{{ base_url }}/account`
- **Headers** : `Authorization: Bearer {{ access_token }}` *(nécessite d'être connecté)*
- **Body (JSON)** :
  ```json
  {
    "password": "PasswordFinal789!"
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Your account has been deleted successfully."
  }
  ```
- **Vérification** : La ligne utilisateur reste en base avec un champ `deleted_at` non nul (Soft Delete), et tous ses tokens d'accès ont été supprimés.

---

## 3. Test de la Traduction (Localisation)

Pour tester la langue des réponses, ajoutez l'en-tête suivant à n'importe quelle requête :

- **Pour l'anglais (par défaut)** :
  ```
  Accept-Language: en
  ```
- **Pour le français** :
  ```
  Accept-Language: fr
  ```
Les messages d'erreur, de confirmation et les emails basculeront automatiquement dans la langue demandée.
