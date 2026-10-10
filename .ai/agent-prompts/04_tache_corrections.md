# 04 — Tâche : corrections après revue (à exécuter avant la tâche 03)

> **Avant de commencer, lis `00_contexte_commun.md` en entier.** Les règles qui y figurent s'appliquent ici.

## Contexte

Les tâches 01 et 02 sont terminées. Une revue a comparé :
- `docs/api/audit_auth_v2.md` : l'état du code **avant** la tâche 02 ;
- `openapi.yaml`  : la documentation **après** les tâches.

Elle a relevé des écarts entre l'intention des prompts 01/02 (`.ai\agent-prompts\01_tache_locale_pays.md`, `.ai\agent-prompts\02_tache_auth_securite.md` ), l'état de départ, et l'OpenAPI. Ce prompt les corrige. **Pour chaque point, vérifie d'abord dans le code** si c'est le code ou la documentation qui diverge, corrige le bon des deux, et dis-le dans ton rapport. Si un point a déjà été traité volontairement (mentionné dans un de tes rapports précédents), laisse-le et signale-le.

Les points marqués **DÉCISION** ont une valeur par défaut ; ne la change que si l'humain l'a demandé.

---

## C1 — Codes d'erreur métier stables (priorité haute)

**Constat.** L'audit montre que le code possède déjà des clés d'erreur métier stables : `otp_invalid`, `otp_expired`, `otp_max_attempts`, `invalid_token`, `credentials_invalid`, `invalid_refresh_token`, `refresh_token_expired`, `device_not_found`. L'OpenAPI, elle, n'expose que `unauthenticated`, `resource_not_found`, `validation_failed`, `too_many_requests`. Son exemple de `ValidationErrorResponse` montre `errors.code: ["The verification code is invalid."]` : le message traduit est exposé, pas la clé. Le front ne peut donc pas distinguer « code faux » de « code expiré » sans lire un texte. Par ailleurs `DELETE /devices/{id}` répond désormais `resource_not_found` alors qu'il répondait `device_not_found`.

**À faire.**
1. Expose chaque clé existante comme **`code` de premier niveau**, en gardant `message` (traduit) et, pour les erreurs de formulaire, `errors.<champ>` :

```json
{
  "code": "otp_invalid",
  "message": "The verification code is invalid.",
  "errors": { "code": ["The verification code is invalid."] }
}
```

2. **Réutilise les noms existants**, ne les renomme pas : cela remplace les noms du catalogue de `02_tache_auth_securite.md` §M quand ils diffèrent (`otp_max_attempts` et non `otp_attempts_exceeded`, `invalid_refresh_token` et non `refresh_token_invalid`, `invalid_token` unique pour les jetons intermédiaires, le contexte étant donné par l'endpoint).
3. Catalogue final, avec statut HTTP inchangé :

| `code` | Statut | Endpoints concernés |
|---|---|---|
| `unauthenticated` | 401 | toutes les routes protégées |
| `validation_failed` | 422 | erreurs de formulaire génériques |
| `credentials_invalid` | 422 | `login` |
| `otp_invalid`, `otp_expired`, `otp_max_attempts` | 422 | tous les `verify-code`, `profile/verify-email`, `verify-pending-email` |
| `invalid_token` | 422 | `register/complete`, `login/verify-code`, `login/resend-code`, `forgot-password/reset`, `verify-pending-email` (selon le jeton) |
| `invalid_refresh_token`, `refresh_token_expired` | 422 | `refresh` |
| `password_current_incorrect` | 422 | `PUT /password`, `DELETE /account` (si ce cas est aujourd'hui une simple règle de validation, ajoute-lui ce code de premier niveau) |
| `pending_email_verification_required` | 403 | `login` (garde `email` et `pending_email` dans le corps) |
| `device_not_found` | 404 | `DELETE /devices/{id}` (appareil inexistant **ou** d'un autre utilisateur) |
| `resource_not_found` | 404 | tout autre 404 |
| `too_many_requests` | 429 | tous (avec `retry_after`) |

4. Le `code` n'est **jamais traduit** ; `message` l'est.
5. Mets à jour l'OpenAPI (voir C8) : un schéma d'erreur avec l'énumération des `code`, et pour **chaque endpoint** les erreurs 422/403/404 réellement possibles avec un exemple chacune.
6. Tests : un test par ligne du tableau, qui vérifie le statut, le `code`, la présence de `message` et, pour les 422 de formulaire, de `errors`.

---

## C2 — Durée de l'access token — DÉCISION

**Constat.** L'audit établit que l'access token expire déjà : `config('sanctum.expiration') = 1440` minutes (1 jour). L'OpenAPI annonce pourtant `expires_in = 3600` (1 heure). La consigne du prompt 02 §B était de **ne changer aucune durée existante** (le défaut de 60 minutes ne valait que si les tokens n'expiraient jamais, ce qui n'était pas le cas).

**À faire.**
- Vérifie la valeur réelle servie par l'API (`expires_in` d'une vraie réponse, et la date d'expiration du token créé).
- **Par défaut : conserve 1440 minutes.** Si le code a été passé à 60 minutes, remets 1440 via la clé de config/`.env` (`access_token_ttl_minutes`). N'applique 60 minutes que si l'humain le demande explicitement.
- `expires_in` doit toujours refléter l'expiration **réelle** du token (test : `expires_in` égal à l'écart entre `now()` et `expires_at` du token créé, avec une marge d'une seconde).
- Dans l'OpenAPI, supprime les valeurs figées de l'introduction (« `expires_in = 3600s` », « `refresh_expires_in = 2592000s` ») : écris « voir `expires_in` et `refresh_expires_in` dans la réponse ». Les exemples peuvent garder des valeurs, mais cohérentes avec la config.

---

## C3 — Limite de vérification OTP plus laxiste qu'avant (priorité haute)

**Constat.** Avant : `auth-otp-verification` = **5/min**. Maintenant : `otp-verify` = **10/min**. Le prompt 02 §H interdisait de réduire une limite existante plus stricte. Ce limiteur protège `register/verify-code`, `register/complete`, `login/verify-code`, `forgot-password/verify-code`, `profile/verify-email`, `verify-pending-email`.

**À faire.** Fixe le défaut de `otp-verify` à **5/min** (même clés qu'avant : email ou jeton + IP, avec repli sur l'IP). Corrige la config, `.env.example`, les descriptions de l'OpenAPI et les tests. Contrôle de la même façon toutes les autres limites : aucune ne doit être plus permissive qu'avant (`login` 5/min, `refresh` ≤ 60/min).

---

## C4 — Nom du jeton de réinitialisation

**Constat.** `forgot-password/verify-code` renvoie `reset_token`, mais `forgot-password/reset` attend le champ `token`. Tous les autres flux réutilisent le même nom d'une étape à l'autre.

**À faire.** Le champ de requête de `forgot-password/reset` devient **`reset_token`** (changement cassant voulu, nothing in production). Mets à jour le `FormRequest`, les tests, l'OpenAPI.

---

## C5 — Un seul endroit pour écrire la langue et le pays — DÉCISION

**Constat.** `PUT /auth/profile` accepte encore `country_id` et `language_id`, en plus de `PATCH /locale`. Deux chemins écrivent la même préférence avec des règles potentiellement différentes (pays inactif accepté ? `null` ?).

**À faire.** Retire `country_id` et `language_id` de `PUT /auth/profile` (requête, `FormRequest`, tests, OpenAPI). `PATCH /api/v2/locale` devient le **seul** point d'écriture. Si un test ou un seed s'appuyait sur l'ancien comportement, adapte-le. Liste le changement dans les changements cassants.

---

## C6 — Contrat exact de `GET /locale` pour les valeurs absentes

**Constat.** L'OpenAPI décrit `country` avec `source: none` possible (donc comme un objet) et ne marque `nullable` ni `LocaleContext.language`, ni `LocaleContext.country`, ni `User.language`, ni `User.country`, alors que `language_id` et `country_id` peuvent être `null`. Le contrat voulu (tâche 01) est `country: null` quand aucun pays n'est résolu.

**À faire.**
1. Appelle réellement `GET /locale` dans les cas : anonyme avec plusieurs pays actifs, anonyme avec un seul pays actif, connecté sans préférence, connecté avec pays inactif. Note le JSON réel.
2. Aligne **le code sur le contrat voulu** : `country: null` quand `country.source` vaudrait `none` ; `language: null` s'il n'y a aucune langue résolue.
3. Aligne l'OpenAPI : `language` et `country` `nullable: true` dans `LocaleContext` ; `CountryDetail.source` ∈ `profile | single_active` (sans `none`) ; `User.language` et `User.country` `nullable: true`.
4. Corrige la description de `country_selection_required` : « `true` quand au moins un pays est actif et qu'aucun pays **actif** n'est résolu (aucun choix, ou pays du profil devenu inactif) ».
5. Tests : un test par cas du point 1, qui vérifie la forme JSON exacte.

---

## C7 — `login/resend-code` sans ambiguïté

**Constat.** `challenge_token` et `email` sont tous deux facultatifs (hérité de l'ancien code). Le front ne sait pas lequel envoyer.

**À faire.** `challenge_token` devient **obligatoire** et unique identifiant (retire le repli par `email`). Un jeton inconnu ou expiré renvoie la même réponse `422` `invalid_token`. Mets à jour le code, les tests et l'OpenAPI.

---

## C8 — Compléter et valider l'OpenAPI

Dans `openapi.yaml` :
1. **En-têtes de réponse** : ajoute `components.headers` pour `Content-Language` (obligatoire sur toutes les réponses, y compris d'erreur) et `Vary`, et référence-les dans les réponses.
2. **Catalogue des erreurs** : cf. C1 (énumération des `code` + exemple par endpoint). Chaque réponse d'erreur 4xx documentée a un `code`, y compris le `403` de `login` (qui n'utilise aujourd'hui aucun schéma d'erreur).
3. **`PATCH /locale`** : documente les règles (au moins une clé requise ; `reset_to_auto` exclusif ; `null` explicite remet un champ en automatique ; pays inactif refusé) et les `422` correspondants avec leurs messages exacts, relevés par exécution réelle.
4. **`AuthTokenResponse.message`** : remplace l'exemple unique (« Logged in successfully. ») par un exemple correct par endpoint (inscription, connexion, refresh). Relève les messages réels.
5. **Langues** : retire `ar` des exemples sauf s'il existe dans la table `languages` ; sinon laisse uniquement `fr` et `en`.
6. **Champs de contact et de licence** (`support@example.com`, URL de licence générique) : ne les invente pas ; laisse-les et signale-les dans le rapport comme « à renseigner par l'humain ».
7. **Validation** : lance un linter sans ajouter de dépendance au projet (`npx --yes @redocly/cli lint <chemin>/openapi.yaml`) et corrige les erreurs ; signale les avertissements restants.
8. **Test de couverture** (Feature) : parse le YAML (le composant `symfony/yaml` est déjà présent avec Laravel), compare les paires méthode + chemin à `Route::getRoutes()` pour `api/v2`, et échoue en nommant les routes absentes de l'OpenAPI et les chemins de l'OpenAPI qui n'existent pas en routes.
9. Vérifie par échantillonnage (au moins une réponse réelle par flux : register, login, refresh, locale, devices, erreurs 422/429) que **la forme documentée correspond à la réponse réelle**. Signale chaque écart.

---

## C9 — Clôture des problèmes de l'audit

Dans ton rapport, ajoute un tableau reprenant les **15 problèmes** de `audit_auth_v2.md` avec : statut (corrigé / partiellement / non corrigé), **preuve** (nom du test ou de la migration), et commentaire. Ces points demandent une vérification particulière :

1. **#7 `verify-pending-email`** : l'audit indique que l'utilisateur est retrouvé par `pending_email` **ou** par `email` avec un `pending_email` non nul. Restreins la recherche à `pending_email` uniquement (c'est l'adresse qui a reçu le code). Ajoute un test : le code ne valide rien si on fournit l'ancienne adresse.
2. **#5 e-mails synchrones** : vérifie que l'envoi est bien différé (`afterResponse()` ou file d'attente) et ajoute un test qui le prouve (par exemple `Notification::fake()` + assertion sur le moment de l'envoi, ou `Queue::fake()`).
3. **#15 suppression de compte** : l'audit se contredit (le tableau dit « `password` requis », le point 15 dit « à vérifier »). Teste : sans mot de passe → 422 ; mauvais mot de passe → 422 avec le bon `code` ; correct → 200 et tous les tokens (access et refresh) révoqués.
4. **#1, #8, #9, #10** : confirme l'existence de la migration (`revoked_reason`, date de révocation, `family_id`) et le test de réutilisation d'un refresh token tourné.
5. Confirme que `family_id` (UUID exposé comme `id` d'appareil) **reste identique après un refresh** et qu'un refresh ne crée **aucun doublon** dans `GET /devices`.

---

## C10 — Internationalisation des messages

**Constat.** L'audit signale que les messages utilisent `__()` avec `lang/en/auth.php` et `lang/fr/auth.php`. Ils sont donc traduits selon la langue résolue, contrairement à l'hypothèse d'un API entièrement anglais.

**À faire.**
1. Compare les clés de `lang/en/` et `lang/fr/` (tous les fichiers) et liste dans le rapport les clés présentes dans l'un et absentes de l'autre.
2. Vérifie par test qu'avec `X-Locale-Override: fr`, les messages métier (`otp_invalid`, `credentials_invalid`, `device_not_found`, 401, 429) sortent en français et que `Content-Language: fr` est présent, tandis que `code` reste identique dans les deux langues.
3. Liste dans le rapport **ce que la langue change réellement** : messages d'authentification, messages de validation Laravel (`lang/*/validation.php` présent ou non ?), objets de notification/emails OTP (sujet et corps), noms de pays et de langues. Cette liste alimente directement le guide (tâche 03, section 3.6).

---

## Définition de terminé

- [ ] Toutes les erreurs métier 4xx ont un `code` du catalogue C1 ; l'OpenAPI le documente par endpoint.
- [ ] Durée de l'access token : valeur effective confirmée, `expires_in` exact, aucune valeur figée en dur dans l'OpenAPI.
- [ ] `otp-verify` à 5/min ; aucune limite plus permissive qu'avant.
- [ ] `forgot-password/reset` utilise `reset_token`.
- [ ] `PUT /auth/profile` n'accepte plus `country_id` / `language_id`.
- [ ] `GET /locale` : formes JSON conformes (valeurs `null`), OpenAPI alignée.
- [ ] `login/resend-code` exige `challenge_token`.
- [ ] OpenAPI complétée, linter exécuté, test de couverture routes/OpenAPI vert, réponses réelles comparées à la documentation.
- [ ] Tableau de clôture des 15 problèmes de l'audit fourni avec preuves.
- [ ] Liste des écarts de traduction fr/en et description de l'effet réel de la langue fournies.
- [ ] Toute la suite de tests passe ; formatage appliqué ; rapport final au format du `00_contexte_commun.md` §5, avec les changements cassants (au minimum : C1 `code` ajoutés, C4 `reset_token`, C5 champs retirés de `PUT /profile`, C7 `challenge_token` obligatoire, éventuel retour à 1440 minutes).
