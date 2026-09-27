# Guide de Test de l'API de Détection de Locale avec Insomnia

Ce guide explique comment tester le système de détection automatique de pays et langue avec **Insomnia** (ou Postman).

---

## 1. Préparation dans Insomnia

### A. Mettre à jour l'environnement Insomnia

Ajoutez les variables suivantes à votre environnement existant :

```json
{
  "base_url": "http://127.0.0.1:8000/api/v2/auth",
  "access_token": "",
  "test_ip": ""
}
```

### B. Lancer le serveur Laravel

```bash
php artisan serve
```

---

## 2. Scénarios de Test du Système de Détection

### Scénario 1 : Détection automatique pour un utilisateur non connecté

Ce scénario teste la détection automatique sans authentification.

#### Test sans headers spécifiques
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/register/send-code`
- **Headers** :
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "email": "test@example.com"
  }
  ```
- **Résultat attendu** : `422` (validation email), mais la détection de locale a fonctionné

#### Test avec header Accept-Language
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/register/send-code`
- **Headers** :
  ```
  Content-Type: application/json
  Accept: application/json
  Accept-Language: fr
  ```
- **Body (JSON)** :
  ```json
  {
    "email": "test@example.com"
  }
  ```
- **Résultat attendu** : La langue détectée doit être le français

#### Test avec header X-Locale-Override
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/register/send-code`
- **Headers** :
  ```
  Content-Type: application/json
  Accept: application/json
  X-Locale-Override: en
  ```
- **Body (JSON)** :
  ```json
  {
    "email": "test@example.com"
  }
  ```
- **Résultat attendu** : La langue doit être l'anglais (override prioritaire)

---

### Scénario 2 : Détection automatique pour un utilisateur connecté

#### 2.1. Créer un compte de test
Suivez le processus d'inscription complet pour obtenir un access_token.

#### 2.2. Vérifier la détection automatique initiale
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/me`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Accept: application/json
  ```
- **Résultat attendu** : L'utilisateur doit avoir un pays et une langue détectés automatiquement (source: auto)

#### 2.3. Modifier manuellement les préférences
- **Méthode** : `PATCH`
- **URL** : `{{ base_url }}/locale`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "country_id": 1,
    "language_id": 2
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Locale preferences updated successfully.",
    "user": {
      "country_id": 1,
      "language_id": 2,
      "country": {
        "id": 1,
        "code": "BF",
        "name": "Burkina Faso",
        "is_active": true
      }
    }
  }
  ```

#### 2.4. Vérifier que les préférences manuelles sont préservées
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/me`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Accept: application/json
  ```
- **Résultat attendu** : Les préférences doivent rester inchangées (source: manual)

#### 2.5. Tester avec X-Locale-Override (doit avoir priorité)
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/me`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Accept: application/json
  X-Locale-Override: fr
  ```
- **Résultat attendu** : La langue de l'application doit être le français (override prioritaire sur tout)

---

### Scénario 3 : Reset vers mode automatique

#### 3.1. Revenir en mode auto
- **Méthode** : `PATCH`
- **URL** : `{{ base_url }}/locale`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "reset_to_auto": true
  }
  ```
- **Résultat attendu** : `200 OK`
  ```json
  {
    "message": "Locale preferences reset to auto-detection.",
    "user": { ... }
  }
  ```

#### 3.2. Vérifier le reset
- **Méthode** : `GET`
- **URL** : `{{ base_url }}/me`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Accept: application/json
  ```
- **Résultat attendu** : Les sources doivent être "auto"

---

### Scénario 4 : Validation de l'endpoint

#### 4.1. Tester sans aucun champ
- **Méthode** : `PATCH`
- **URL** : `{{ base_url }}/locale`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {}
  ```
- **Résultat attendu** : `422 Unprocessable Content` avec erreur "At least one of country_id or language_id must be provided."

#### 4.2. Tester avec country_id invalide
- **Méthode** : `PATCH`
- **URL** : `{{ base_url }}/locale`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "country_id": 99999
  }
  ```
- **Résultat attendu** : `422 Unprocessable Content` avec erreur "The selected country is invalid."

#### 4.3. Tester avec language_id invalide
- **Méthode** : `PATCH`
- **URL** : `{{ base_url }}/locale`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "language_id": 99999
  }
  ```
- **Résultat attendu** : `422 Unprocessable Content` avec erreur "The selected language is invalid."

---

### Scénario 5 : Modification partielle (un seul champ)

#### 5.1. Modifier uniquement le pays
- **Méthode** : `PATCH`
- **URL** : `{{ base_url }}/locale`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "country_id": 1
  }
  ```
- **Résultat attendu** : `200 OK` avec seulement le pays modifié (langue inchangée)

#### 5.2. Modifier uniquement la langue
- **Méthode** : `PATCH`
- **URL** : `{{ base_url }}/locale`
- **Headers** :
  ```
  Authorization: Bearer {{ access_token }}
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON)** :
  ```json
  {
    "language_id": 2
  }
  ```
- **Résultat attendu** : `200 OK` avec seulement la langue modifiée (pays inchangé)

---

### Scénario 6 : Test de géolocalisation IP (avec package installé)

#### 6.1. Configurer une IP de test
Ajoutez ceci à votre fichier `.env` :
```
TEST_IP=102.164.48.2  # IP du Burkina Faso
```

#### 6.2. Créer un nouvel utilisateur sans préférences
Inscrivez-vous et vérifiez que le pays détecté correspond à l'IP configurée.

#### 6.3. Tester avec IP locale (fallback)
```bash
# Dans .env
TEST_IP=127.0.0.1
```
Le système doit utiliser le pays par défaut (Burkina Faso).

---

## 3. Vérification en Base de Données

Pour vérifier l'état des préférences en base de données :

```sql
SELECT id, email, country_id, language_id, country_source, language_source 
FROM users 
ORDER BY id DESC 
LIMIT 5;
```

Ou avec Artisan :
```bash
php artisan tinker --execute 'User::latest()->get(["id", "email", "country_id", "language_id", "country_source", "language_source"]);'
```

---

## 4. Résumé des Endpoints

| Méthode | Route | Authentifié ? | Description |
| :--- | :--- | :---: | :--- |
| **PATCH** | `/api/v2/auth/locale` | **Oui** | Modifier les préférences de pays/langue |
| **GET** | `/api/v2/auth/me` | **Oui** | Voir les préférences actuelles |
| **POST** | `/api/v2/auth/register/send-code` | Non | Tester la détection pour non-authentifié |

---

## 5. Points Importants à Vérifier

1. **Priorité X-Locale-Override** : Doit toujours avoir la priorité maximale
2. **Préférences manuelles** : Ne doivent jamais être écrasées par la détection automatique
3. **Valeurs auto existantes** : Ne doivent pas être re-détectées tant qu'elles existent
4. **Fallbacks** : Le système doit toujours avoir une valeur de fallback
5. **Pays inactifs** : Doivent retourner un warning dans la réponse

---

## 6. Dépannage

### Problème : La détection ne fonctionne pas
**Solution** : Vérifiez que le package `stevebauman/location` est installé (`composer install`).

### Problème : Les préférences manuelles sont écrasées
**Solution** : Vérifiez que les colonnes `country_source` et `language_source` sont bien 'manual'.

### Problème : Le header X-Locale-Override ne fonctionne pas
**Solution** : Vérifiez que le middleware `DetectLocaleMiddleware` est bien enregistré dans `bootstrap/app.php`.

### Problème : L'endpoint retourne 404
**Solution** : Vérifiez que la route est bien définie dans `routes/api.php` et que vous êtes authentifié.

---

## 7. Notes pour le Développement

- Le package `stevebauman/location` doit être installé avec `composer install`
- La variable d'environnement `TEST_IP` peut être utilisée pour tester différentes géolocalisations
- Les modifications de préférences sont immédiatement persistées en base de données
- Le système fonctionne aussi pour les utilisateurs non authentifiés (sans persistance)
