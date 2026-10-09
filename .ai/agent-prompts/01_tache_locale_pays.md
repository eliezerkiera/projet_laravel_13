# 01 — Tâche : refonte de la gestion de la langue et du pays (back)

> **Avant de commencer, lis `.ai/agent-prompts/00_contexte_commun.md` en entier.** Les règles qui y figurent s'appliquent ici.

## Objectif

Rendre le système de langue/pays **simple et explicite pour le front** :

1. Le back applique réellement la priorité prévue, **profil utilisateur compris** (aujourd'hui le profil est ignoré, voir hypothèse H1).
2. Le back **dit toujours** au front quelle langue et quel pays il utilise, et pourquoi. Le front n'a rien à deviner.
3. Plus aucune écriture en base pendant la détection. Seul un choix explicite via `PATCH` modifie le profil.

**Prérequis :** aucun. Cette tâche s'exécute en premier.

---

## Étape 0 — Audit et preuve (aucune modification de code applicatif)

1. Lis : `bootstrap/app.php`, `DetectLocaleMiddleware`, `LocaleDetectionService`, `LocaleController`, `UpdateLocaleRequest`, `UserResource`, `routes/api.php`, modèles `User`, `Country`, `Language`, leurs migrations et factories/seeders, `config/cors.php`, `config/app.php`, `config/auth.php`.
2. Réponds par écrit, dans ton rapport, à ces questions (avec le fichier et la ligne) :
   - Le guard par défaut de `config/auth.php` est-il `web` ou `sanctum` ?
   - La table `languages` a-t-elle une colonne d'activation (`is_active` ou équivalent) ?
   - Comment `countries` référence-t-elle sa langue (`language_id` ?) et quelle relation Eloquent existe ?
   - `UserResource` expose-t-il `country_source` / `language_source` ? Où d'autre ces deux colonnes sont-elles lues ou écrites (`grep` dans tout le dépôt, y compris tests, seeders, factories) ?
   - Où `detected_country`, `detected_language`, `country_source`, `language_source` (attributs de requête) sont-ils lus ?
   - `UpdateLocaleRequest` accepte-t-il `{"reset_to_auto": true}` seul ?
   - Les messages d'API utilisent-ils `__()` / `trans()` avec des fichiers de traduction `fr` et `en`, ou sont-ils codés en dur en anglais ? Quels fichiers existent dans `lang/` ?
3. **Prouve H1** : écris un test Feature qui crée un utilisateur avec `language_id` = anglais, appelle une route API avec son Bearer token et **sans** `Accept-Language`, puis vérifie que la langue appliquée est l'anglais. Il doit **échouer** avant ton correctif (rouge). Garde-le : il deviendra un test de non-régression.

---

## Étape 1 — Service de résolution (sans effet de bord)

Refonds `LocaleDetectionService` (ou crée `App\Services\Locale\LocaleResolver` et laisse l'ancien service comme façade fine si d'autres classes en dépendent). Les squelettes ci-dessous sont **indicatifs** : adapte-les aux conventions du projet et à la version de PHP.

### 1.1 Objet de résultat

```php
final readonly class LocaleContext
{
    public function __construct(
        public ?Language $language,
        public string $languageSource, // override | profile | accept_language | country | default
        public ?Country $country,
        public string $countrySource,  // profile | single_active | none
    ) {}
}
```

### 1.2 Résolution

L'utilisateur est lu avec **`$request->user('sanctum')`**, car le middleware s'exécute avant `auth:sanctum`. Un token absent, invalide ou expiré donne `null` (traité comme anonyme, jamais d'erreur 500 ni 401 ici).

**Langues supportées** : lues dans la table `languages` (uniquement les actives s'il existe une colonne d'activation), indexées par `code`, mémorisées pour la durée de la requête. Plus aucune liste `['fr','en']` en dur.

**Normalisation d'un code** : minuscules, trim, et on garde la partie avant le premier `-` ou `_` (`EN-us` → `en`, `fr_FR` → `fr`).

**Langue**, première étape qui aboutit :

| # | Source | Condition | `languageSource` |
|---|---|---|---|
| 1 | Header `X-Locale-Override` | code normalisé présent dans les langues supportées | `override` |
| 2 | Profil | `user->language` existe et est supportée | `profile` |
| 3 | `Accept-Language` | une langue du header, **dans l'ordre des préférences `q`**, est supportée | `accept_language` |
| 4 | Pays résolu | `country->language` existe et est supportée | `country` |
| 5 | Défaut | langue dont le code vaut `config('app.locale')` (peut être `null` si absente en base) | `default` |

Pour l'étape 3, **n'utilise pas `getPreferredLanguage()`** (il renvoie silencieusement le premier élément de la liste en cas d'échec). Parcours `$request->getLanguages()` (déjà triée par qualité), normalise chaque entrée, retiens la première supportée. Si aucune ne l'est, passe à l'étape 4.

**Pays**, première étape qui aboutit :

| # | Condition | `countrySource` |
|---|---|---|
| 1 | `user->country_id` renseigné (le pays est renvoyé **même s'il est devenu inactif**) | `profile` |
| 2 | Il existe **exactement un** pays actif | `single_active` |
| 3 | Sinon (zéro ou plusieurs pays actifs, pas de profil) | `none` (pays `null`) |

Il n'existe **aucun** header de pays et aucune détection par IP.

### 1.3 `country_selection_required`

Booléen calculé avec : `activeCount >= 1 && !(country !== null && country->is_active)`.

| Situation | Valeur |
|---|---|
| Un seul pays actif, utilisateur anonyme | `false` |
| Plusieurs pays actifs, aucun choix | `true` |
| Plusieurs pays actifs, profil avec pays actif | `false` |
| Profil avec un pays devenu inactif, au moins un pays actif existe | `true` |
| Aucun pays actif | `false` |

### 1.4 Application

`apply(LocaleContext)` fait `app()->setLocale($context->language?->code ?? config('app.locale', 'en'))`.

### 1.5 À supprimer

- Les deux blocs `$user->update(...)` d'auto-persistance de `detect()`.
- La détection par IP n'existe pas : n'en ajoute pas.

---

## Étape 2 — Middleware

Modifie `DetectLocaleMiddleware` :

```php
public function handle(Request $request, Closure $next): Response
{
    try {
        $context = $this->resolver->resolve($request);
        $this->resolver->apply($context);
        $request->attributes->set('locale_context', $context);
        // Si l'audit montre que detected_country / detected_language sont lus ailleurs,
        // conserve-les ; sinon supprime-les.
    } catch (\Throwable $e) {
        report($e); // plus d'erreur avalée en silence
        app()->setLocale(config('app.locale', 'en'));
    }

    $response = $next($request);

    $response->headers->set('Content-Language', app()->getLocale());
    $response->setVary(['Accept-Language', 'X-Locale-Override', 'Authorization'], false);

    return $response;
}
```

Exigences :
- `Content-Language` doit être présent sur **toutes** les réponses API, y compris les erreurs 401, 404, 422 et 429. Vérifie par test que le pipeline de Laravel passe bien par ton middleware pour les réponses d'exception.
- Le middleware reste **global** (`append`) : la langue doit être appliquée aussi aux routes publiques.

### CORS (indispensable pour un front web)

Ouvre `config/cors.php` et vérifie :
- `allowed_headers` autorise `X-Locale-Override` (soit `['*']`, soit la liste explicite).
- `exposed_headers` contient `Content-Language` (et `Retry-After`, utilisé par la tâche 02).
Sans cela, un front navigateur ne peut ni envoyer ni lire ces headers. Corrige si besoin.

---

## Étape 3 — Nouvel endpoint public `GET /api/v2/locale`

Il fonctionne **à l'identique** pour un anonyme et un connecté (le Bearer est facultatif). C'est l'unique appel dont le front a besoin pour s'initialiser.

- Route : `GET /api/v2/locale` (hors groupe `auth:sanctum`), nom `v2.locale.show`.
- Contrôleur : `LocaleController@show` (utilise un `LocaleResource` ou un petit presenter partagé, car la même structure est renvoyée par `PATCH`).

Réponse `200` :

```json
{
  "language": { "id": 1, "code": "fr", "name": "Français", "source": "profile" },
  "country":  { "id": 1, "code": "BF", "name": "Burkina Faso", "native_name": "Burkina Faso", "phone_code": "+226", "is_active": true, "source": "single_active" },
  "country_selection_required": false,
  "preferences": { "language_id": 1, "country_id": null },
  "supported_languages": [
    { "id": 1, "code": "fr", "name": "Français" },
    { "id": 2, "code": "en", "name": "English" }
  ],
  "countries": [
    { "id": 1, "code": "BF", "name": "Burkina Faso", "native_name": "Burkina Faso", "phone_code": "+226",
      "language": { "id": 1, "code": "fr", "name": "Français" } }
  ]
}
```

Règles :
- `language` vaut `null` si aucune langue n'a pu être résolue (cas `default` sans ligne en base) ; `language.source` ∈ `override | profile | accept_language | country | default`.
- `country` vaut `null` quand `countrySource = none`. Sinon `country.source` ∈ `profile | single_active`. Pour `none`, expose `"country": null` (le front lit `country_selection_required`).
- `preferences` : valeurs **brutes du profil** (`null` = « Automatique »). Pour un anonyme, `{ "language_id": null, "country_id": null }`. Cela permet au front d'afficher « Automatique » dans les réglages.
- `countries` : les pays **actifs** seulement, avec leur langue (même forme que l'existant `/countries/active`).
- `supported_languages` : les langues réellement acceptées par le back (celles de la table).
- Les noms n'ont pas besoin d'être traduits ; reprends les champs existants.

### `GET /api/v2/countries/active`

Conserve la route et **la forme actuelle de sa réponse**, mais fais-la s'appuyer sur la même logique (service partagé) pour qu'aucun calcul ne soit dupliqué. `country_selection_required` suit la formule §1.3.

---

## Étape 4 — `PATCH /api/v2/locale` (déplacé et corrigé)

**Changement cassant voulu :** la route passe de `PATCH /api/v2/auth/locale` à `PATCH /api/v2/locale` (même chemin que le `GET`). Supprime l'ancienne route. Protégée par `auth:sanctum`.

### 4.1 Corps accepté

| Champ | Type | Effet |
|---|---|---|
| `language_id` | entier ou `null` | entier : choisit cette langue ; `null` explicite : remet la **langue seule** en automatique |
| `country_id` | entier ou `null` | entier : choisit ce pays ; `null` explicite : remet le **pays seul** en automatique |
| `reset_to_auto` | booléen | `true` : met `language_id` **et** `country_id` à `null` |

Détecte la présence d'une clé avec `$request->has()` / `array_key_exists`, **pas** avec `filled()` (sinon `null` est indiscernable de « absent »).

### 4.2 Validation (`UpdateLocaleRequest`)

- Au moins une des trois clés doit être présente, sinon `422` : `"At least one of country_id, language_id or reset_to_auto must be provided."`
- `reset_to_auto: true` **ne peut pas** être combiné avec `country_id` ou `language_id` → `422` : `"reset_to_auto cannot be combined with country_id or language_id."`
- `country_id` (si entier) : doit exister → sinon `"The selected country is invalid."` ; doit être **actif** → sinon `"The selected country is not available."`
- `language_id` (si entier) : doit exister (et être actif s'il y a une colonne d'activation) → sinon `"The selected language is invalid."`
- Réponses de validation au format Laravel standard (`message` + `errors`).

### 4.3 Comportement

- Un seul `update()` atomique avec les seuls champs concernés (plus de double `update`).
- Plus aucun champ `*_source` écrit (voir §5).
- Plus de champ `warning` : un pays inactif est désormais refusé à l'enregistrement. Un pays **devenu** inactif après coup reste visible via `country.is_active = false` dans `GET /locale`, avec `country_selection_required = true`.

### 4.4 Réponse `200`

```json
{
  "message": "Locale preferences updated successfully.",
  "locale": { "...même structure exacte que GET /api/v2/locale..." },
  "user": { "...UserResource..." }
}
```

Pour `reset_to_auto` (ou une réinitialisation par `null`) : `message` = `"Locale preferences reset to auto-detection."`.

**Important :** le bloc `locale` doit refléter l'état **après** la mise à jour, pas celui calculé au début de la requête par le middleware. Recalcule le contexte avec l'utilisateur rafraîchi. Si la requête porte un `X-Locale-Override`, il reste prioritaire dans ce calcul (et `language.source` le dira).

---

## Étape 5 — Nettoyage des `*_source`

1. Retire `country_source` et `language_source` de `UserResource` et de toute réponse JSON.
2. Si l'audit confirme qu'elles ne sont plus utilisées nulle part, crée une **nouvelle migration** qui supprime ces deux colonnes de `users` (avec `down()` qui les recrée). Mets à jour modèle (`$fillable`/`$casts`), factories, seeders et tests.
3. Si elles sont encore utilisées ailleurs, ne les supprime pas : arrête de les exposer et signale-le dans le rapport.

---

## Étape 6 — Alignement de `register/complete`

Dans `POST /api/v2/auth/register/complete` (uniquement les règles liées à la locale, ne touche à rien d'autre : la tâche 02 s'en occupe) :

- `country_id` : **obligatoire s'il y a plus d'un pays actif**, facultatif sinon (s'il y a un seul pays actif, ou aucun, le profil reste en automatique si le champ est absent). Si présent : doit exister et être actif.
- `language_id` : facultatif (`nullable`). Si présent : doit exister (et être active).
- Les valeurs absentes restent `null` dans le profil (= automatique). Aucune valeur n'est écrite « par défaut » sans que le client l'ait demandée.

---

## Étape 7 — Audit de l'internationalisation (rapport uniquement)

Sans rien modifier, indique précisément dans ton rapport **ce que la langue change aujourd'hui** dans les réponses : messages de validation Laravel, messages métier des contrôleurs, noms de pays/langues, emails OTP (sujet et contenu). Liste les fichiers de `lang/` présents (`fr`, `en`) et lesquels sont absents.
L'objectif est que le guide (tâche 03) dise la vérité sur ce point. Ne traduis pas massivement des messages.

---

## Tests à écrire (Feature)

Utilise des `Country` actifs/inactifs et des `Language` `fr` / `en` créés par factory ou seeder de test.

### Résolution (via `GET /api/v2/locale`)

| # | Scénario | Résultat attendu |
|---|---|---|
| 1 | Anonyme, aucun header, 1 pays actif (langue `fr`) | `language` = fr / `country` ; `country` = ce pays / `single_active` ; `country_selection_required` = false |
| 2 | Anonyme, aucun header, 2 pays actifs | `language.source` = `default` ; `country` = null ; `country_selection_required` = true |
| 3 | `Accept-Language: en` | en / `accept_language` |
| 4 | `Accept-Language: de`, pays unique dont la langue est **en** | en / `country` (prouve qu'il n'y a plus de repli `fr` caché) |
| 5 | `Accept-Language: fr-FR,fr;q=0.9,en;q=0.8` | fr / `accept_language` |
| 6 | `Accept-Language: en;q=0.9, fr;q=0.8` | en |
| 7 | `X-Locale-Override: en` + `Accept-Language: fr` | en / `override` |
| 8 | `X-Locale-Override: de` (non supporté) + `Accept-Language: en` | en / `accept_language` (override ignoré) |
| 9 | `X-Locale-Override: EN-us` | en / `override` (normalisation) |
| 10 | Connecté, profil en, `Accept-Language: fr` | en / `profile` |
| 11 | Connecté, profil en, `X-Locale-Override: fr` | fr / `override`, et la base **n'a pas changé** |
| 12 | Connecté sans préférence, `Accept-Language: fr` | fr / `accept_language`, et `users.language_id` **reste `null`** après la requête |
| 13 | Connecté, profil avec pays actif, 2 pays actifs | `country.source` = `profile`, `country_selection_required` = false |
| 14 | Connecté, profil avec pays inactif, 1 pays actif existant | `country.is_active` = false, `country_selection_required` = true |
| 15 | Bearer token invalide / expiré | traité comme anonyme, `200` (pas de 500) |
| 16 | `preferences` reflète les valeurs brutes du profil (`null` quand automatique) | idem |

### En-têtes

- `Content-Language` présent et correct sur des réponses `200`, `401`, `404` et `422`.
- `Vary` contient `Accept-Language` et `X-Locale-Override`.
- `app()->getLocale()` suit la langue résolue (test de non-régression de H1).

### `PATCH /api/v2/locale`

- Mise à jour de la langue seule, du pays seul, des deux ; l'autre champ reste intact.
- `language_id: null` remet la langue seule en automatique, le pays reste.
- `reset_to_auto: true` met les deux à `null` (vérifier en base) ; une requête suivante **ne les remplit pas** (plus d'auto-persistance).
- `{}` → 422 ; `reset_to_auto` + `country_id` → 422 ; pays inexistant → 422 ; pays inactif → 422 ; langue inexistante → 422.
- Sans token → 401.
- Le bloc `locale` de la réponse reflète l'état après mise à jour.
- L'ancienne route `PATCH /api/v2/auth/locale` n'existe plus (404/405).

### `register/complete`

- Plusieurs pays actifs sans `country_id` → 422.
- Un seul pays actif sans `country_id` → 201, profil en automatique.
- Pays inactif → 422.

---

## Définition de terminé

- [ ] Le test de preuve H1 est passé de rouge à vert.
- [ ] `GET /api/v2/locale` existe, public, conforme au schéma ci-dessus.
- [ ] `PATCH /api/v2/locale` conforme (corps, validation, réponse avec bloc `locale`).
- [ ] Aucune écriture en base pendant la détection (test 12).
- [ ] `Content-Language` sur toutes les réponses API ; CORS vérifié.
- [ ] Plus de liste de langues codée en dur ; plus de repli silencieux vers `fr`.
- [ ] `*_source` retirés des réponses (colonnes supprimées si inutilisées).
- [ ] Toute la suite de tests passe ; formatage appliqué.
- [ ] Rapport final au format du `00_contexte_commun.md` §5, avec la liste des changements cassants (au minimum : route `PATCH /locale` déplacée, `*_source` retirés, `warning` supprimé, priorité profil > `Accept-Language` désormais effective, `reset_to_auto` remet vraiment à `null`).
