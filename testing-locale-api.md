# Tester l’API de langue et de pays

## Contexte

Le middleware de l’API résout la langue de chaque requête et, lorsqu’un seul pays est actif, le pays associé. Aucun pays n’est déduit de l’adresse IP.

Les headers importants sont :

- `Accept-Language` : header HTTP standard utilisé pour choisir `fr` ou `en` en l’absence de préférence enregistrée.
- `X-Locale-Override` : header propre au projet qui force la langue de la requête, par exemple `en`. Il ne modifie pas les préférences sauvegardées.

Dans les exemples, remplace `{{ base_url }}` par l’URL locale de l’API, par exemple `http://localhost:8000`, et `{{ token }}` par un jeton Sanctum valide.

## Données de test

Charge les données de référence en environnement local avec :

```bash
php artisan db:seed --class=DatabaseSeeder --no-interaction
```

Le seeder laisse uniquement le Burkina Faso (`BF`) actif. Les langues `fr` et `en` doivent exister dans `languages` et le Burkina Faso doit référencer `fr` avec `language_id`.

Pour simuler plusieurs pays actifs, active par exemple le Sénégal (`SN`) dans la table `countries` avec votre outil de base de données :

```sql
UPDATE countries SET is_active = 1 WHERE code = 'SN';
```

Remettez ensuite `SN` à `0` pour revenir au scénario d’un seul pays actif. Utilisez un compte de test dont `country_id` et `language_id` sont `NULL` pour les scénarios d’auto-résolution, et un autre ayant les deux sources à `manual` pour tester le verrouillage.

## Authentification Insomnia

L’API utilise Sanctum avec un Bearer token, obtenu après la connexion en deux étapes :

1. Envoyez `POST {{ base_url }}/api/v2/auth/login` avec un JSON tel que `{ "email": "test@example.com", "password": "password" }`.
2. Récupérez le code OTP reçu dans l’environnement de développement, puis envoyez `POST {{ base_url }}/api/v2/auth/login/verify-code` avec :

```json
{
  "challenge_token": "<challenge_token de l’étape 1>",
  "code": "123456",
  "device_name": "Insomnia"
}
```

3. Copiez `access_token` depuis la réponse. Dans Insomnia, choisissez **Auth → Bearer Token**, ou ajoutez `Authorization: Bearer {{ token }}` aux requêtes authentifiées.

## Scénarios à tester

### 1. Visiteur sans header

Envoyez `GET {{ base_url }}/api/v2/countries/active`, sans authentification ni header.

Avec un seul pays actif, la réponse doit contenir `country_selection_required: false` et uniquement `BF`. Le middleware résout aussi la langue `fr` depuis la langue de ce pays, sans rien persister car le visiteur n’est pas connecté.

Avec plusieurs pays actifs, la réponse contient `country_selection_required: true` et la liste complète des pays actifs. Aucun pays n’est choisi automatiquement.

### 2. Visiteur avec `Accept-Language`

Envoyez la même requête avec `Accept-Language: en`, puis avec `Accept-Language: fr`.

La langue de la requête doit être respectivement `en` et `fr`. Utilisez une réponse API traduite ou les logs de développement si vous devez constater la locale appliquée ; le endpoint des pays reste volontairement identique.

### 3. Visiteur avec override

Envoyez `GET {{ base_url }}/api/v2/countries/active` avec :

```http
X-Locale-Override: en
Accept-Language: fr
```

La locale active est `en` : l’override est prioritaire. Aucune préférence utilisateur n’est créée ou modifiée.

### 4. Utilisateur sans préférence

Envoyez `GET {{ base_url }}/api/v2/auth/me` avec `Authorization: Bearer {{ token }}` pour un compte ayant `country_id` et `language_id` à `NULL`.

Avec uniquement `BF` actif, la réponse `user` doit montrer le Burkina Faso et la langue `fr`; en base, `country_source` et `language_source` deviennent `auto`.

Après activation de `SN`, rejouez exactement la même requête avec un nouveau compte sans préférence. `country_id` reste `NULL`, aucune source de pays n’est écrite, et l’application utilise `APP_LOCALE` lorsqu’aucun header de langue n’est envoyé.

### 5. Utilisateur avec préférence manuelle

Pour un compte dont `country_source` et `language_source` valent `manual`, envoyez :

```http
GET {{ base_url }}/api/v2/auth/me
Authorization: Bearer {{ token }}
Accept-Language: en
```

La réponse doit conserver le pays et la langue enregistrés. Changez le header autant que nécessaire : il ne doit pas écraser les données. `X-Locale-Override` est l’exception de requête : il force seulement la locale temporaire, sans persister de changement.

### 6. Modifier manuellement les préférences

Envoyez :

```http
PATCH {{ base_url }}/api/v2/auth/locale
Authorization: Bearer {{ token }}
Content-Type: application/json
```

```json
{
  "country_id": 1,
  "language_id": 2
}
```

Remplacez les identifiants par ceux retournés par `GET /api/v2/countries/active` et par une ligne existante de `languages`. La réponse 200 contient l’utilisateur mis à jour. Vérifiez ensuite avec `GET /api/v2/auth/me` et `Accept-Language: en` : les valeurs manuelles restent inchangées. Une requête sans `country_id`, `language_id` ni `reset_to_auto` retourne 422.

### 7. Liste de sélection des pays

Envoyez :

```http
GET {{ base_url }}/api/v2/countries/active
```

Exemple de réponse avec plusieurs pays actifs :

```json
{
  "country_selection_required": true,
  "countries": [
    {
      "id": 1,
      "code": "BF",
      "name": "Burkina Faso",
      "native_name": "Burkina Faso",
      "phone_code": "+226",
      "language": { "id": 1, "code": "fr", "name": "French" }
    }
  ]
}
```

Le client doit afficher un sélecteur lorsque `country_selection_required` est `true`, puis sauvegarder le choix avec `PATCH /api/v2/auth/locale` après authentification.

## Collection Insomnia minimale

Créez un environnement avec `base_url` et `token`, puis ces requêtes :

1. **Pays actifs — visiteur** : `GET {{ base_url }}/api/v2/countries/active`.
2. **Pays actifs — anglais** : même requête, header `Accept-Language: en`.
3. **Pays actifs — override** : même requête, headers `Accept-Language: fr` et `X-Locale-Override: en`.
4. **Mon profil — auto** : `GET {{ base_url }}/api/v2/auth/me`, Bearer `{{ token }}`.
5. **Préférence manuelle** : `PATCH {{ base_url }}/api/v2/auth/locale`, Bearer `{{ token }}`, avec le JSON de la section 6.
6. **Mon profil — vérification** : reprenez la requête 4 avec `Accept-Language: en` pour confirmer que la préférence manuelle n’a pas été écrasée.
