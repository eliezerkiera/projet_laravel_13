# 02 — Tâche : cohérence, sécurité et prévisibilité de l'authentification (back)

> **Avant de commencer, lis `.ai/agent-prompts/00_contexte_commun.md` en entier.** Les règles qui y figurent s'appliquent ici.

## Objectif

Rendre l'API d'authentification **sûre et prévisible pour le front**, sans toucher à la langue/pays (tâche 01) :

- chaque réponse de token dit combien de temps il vit ;
- les mêmes données utilisateur sont renvoyées partout ;
- chaque erreur métier a un `code` stable ;
- les refresh tokens sont protégés contre la réutilisation ;
- les flux OTP, les limites de débit, la fermeture de sessions et la suppression de compte suivent des règles claires.

**Prérequis :** la tâche 01 est terminée et ses tests passent.

**Rappel de périmètre :** dans `register/complete`, ne touche pas aux champs `country_id` / `language_id` (déjà traités par la tâche 01).

---

## Section A — Audit préalable (aucune modification de code applicatif)

Lis **tous** les contrôleurs, services, `FormRequest`, `Resource`, modèles, migrations, middlewares et la config concernant l'authentification, plus `routes/api.php`, `config/sanctum.php`, `config/auth.php`, `app/Providers/AppServiceProvider.php` et les tests existants.

Produis le fichier **`docs/api/audit_auth_v2.md`** (en français) contenant, **pour chaque route auth** (`php artisan route:list --path=api/v2 --json` fait foi) :

| Colonne | Contenu |
|---|---|
| Méthode + chemin | exact |
| Auth requise | oui / non, quel middleware |
| Contrôleur@méthode, FormRequest | classes réelles |
| Limite de débit actuelle | nom du limiteur et valeurs, ou « aucune » |
| Corps attendu | champs et règles de validation |
| Réponses | codes HTTP et messages **exacts** (succès et erreurs) |

Puis une section « Modèle de données » décrivant : où et comment sont stockés les OTP (haché ou en clair, durée de vie, nombre d'essais, une ligne par `purpose` ?), les refresh tokens (colonnes, lien avec les access tokens, notion d'appareil), les `challenge_token` / `verification_token` / `reset_token` (JWT, ligne en base, cache ?), et la durée de vie **effective** de chacun (`config/sanctum.php` `expiration`, constantes, `Carbon::addMinutes(...)`).

Réponds en particulier à ces questions dans l'audit :

1. L'**access token** Sanctum expire-t-il réellement (`expiration` non `null` ou `expires_at` à la création) ?
2. Un **appareil** correspond-il à un `personal_access_tokens.id` ? Son `id` **change-t-il à chaque refresh** ? Un refresh crée-t-il un doublon dans `GET /devices` ?
3. Le refresh token révoqué par rotation est-il distinguable d'un token révoqué par logout (aucune trace de la raison aujourd'hui ?) ?
4. Quelle est la différence réelle entre `POST /auth/verify-pending-email` (publique) et `POST /auth/profile/verify-email` (protégée) ?
5. Les OTP de purposes différents (inscription, connexion, reset, email) sont-ils **isolés** (un code d'inscription ne doit pas valider une connexion) ?
6. Les emails OTP sont-ils envoyés de manière synchrone ?

**Arrête-toi après l'audit uniquement si une réponse remet en cause un choix de ce prompt de façon importante** (par exemple : le refresh token n'existe pas en base). Sinon, continue.

---

## Section B — Configuration centralisée des durées et limites

Crée (ou étends) un fichier de config, par exemple `config/auth_api.php`, alimenté par des variables d'environnement, et documente-les dans `.env.example`.

**Règle absolue :** les valeurs par défaut reprennent les durées **effectives actuelles** relevées à l'audit. Tu ne changes aucune durée existante, sauf pour l'access token s'il n'expire jamais (voir section C).

| Clé de config | Rôle | Valeur par défaut |
|---|---|---|
| `access_token_ttl_minutes` | durée de vie de l'access token | valeur actuelle, sinon **60** |
| `refresh_token_ttl_days` | durée de vie du refresh token | valeur actuelle |
| `otp_ttl_minutes` | validité d'un code OTP | valeur actuelle |
| `otp_max_attempts` | essais autorisés avant invalidation du code | valeur actuelle, sinon **5** |
| `otp_resend_delay_seconds` | délai minimal entre deux envois | valeur actuelle, sinon **60** |
| `verification_token_ttl_minutes`, `challenge_token_ttl_minutes`, `reset_token_ttl_minutes` | validité des jetons intermédiaires | valeurs actuelles |
| `rate_limits.*` | voir section H | voir section H |

Remplace dans le code les durées en dur par ces clés.

---

## Section C — `expires_in` sur toutes les réponses de token

1. Si l'audit montre que l'access token **n'expire jamais**, fais-le expirer : crée-le avec `createToken($name, $abilities, now()->addMinutes(config('auth_api.access_token_ttl_minutes')))` et vérifie que `config('sanctum.expiration')` ne contredit pas ce choix. Signale le changement dans le rapport (changement de comportement important).
2. Ajoute à **chaque** réponse qui émet un access token (`register/complete`, `login/verify-code`, `refresh`) :

```json
{
  "access_token": "...",
  "refresh_token": "...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_expires_in": 2592000,
  "user": { }
}
```

`expires_in` et `refresh_expires_in` sont des **entiers en secondes**, calculés depuis la date d'expiration réelle du token créé (et non depuis la config), pour rester exacts.

3. Les jetons intermédiaires exposent aussi leur durée dans la réponse qui les émet : `verification_token` (`register/verify-code`), `challenge_token` (`login`), `reset_token` (`forgot-password/verify-code`), avec un champ `expires_in` (secondes).

---

## Section D — Payload utilisateur unifié

1. Utilise **le même `UserResource`** dans toutes les réponses qui renvoient un utilisateur : `register/complete`, `login/verify-code`, `refresh`, `me`, `profile`.
   Aujourd'hui `/refresh` renvoie un `user` réduit (`id`, `email`) ; ce n'est plus accepté.
2. `UserResource` expose au minimum : `id`, `first_name`, `last_name`, `email`, `email_verified_at` (s'il existe), `pending_email` (s'il existe), `country_id`, `language_id`, et les objets `country` et `language` **toujours chargés** dans les réponses d'authentification (eager loading pour éviter le N+1 et éviter au front un second appel).
3. Aucun champ interne (hash, tokens, `*_source`) n'est exposé.

---

## Section E — Refresh tokens : rotation durcie et détection de réutilisation

### Comportement cible

| Situation | Résultat |
|---|---|
| Refresh valide | Nouveau couple access + refresh. L'ancien refresh est marqué **tourné** (`revoked_reason = 'rotated'`, `revoked_at`/`rotated_at` renseigné). L'ancien access token de cet appareil est révoqué. |
| Refresh inconnu | `422` code `refresh_token_invalid` |
| Refresh expiré | `422` code `refresh_token_expired`, message actuel conservé |
| Refresh révoqué (logout, changement de mot de passe, suppression d'appareil) | `422` code `refresh_token_invalid` |
| **Refresh déjà tourné présenté à nouveau** (réutilisation) | `422` code `refresh_token_invalid` **et** révocation de **toute la session** (la « famille » : tous les refresh et access tokens issus du même appareil). Écris un `Log::warning('refresh_token_reuse_detected', [...])` avec `user_id` et identifiant d'appareil, jamais le token. |

### Implémentation

- Nouvelle migration : ajoute au modèle de refresh token ce qui manque parmi `revoked_reason` (nullable string : `rotated`, `logout`, `reuse_detected`, `password_changed`, `device_revoked`, `account_deleted`), une date de révocation, et un `family_id` (uuid) hérité à chaque rotation, **si** l'audit montre qu'il n'existe pas d'équivalent. Réutilise les colonnes existantes quand elles font déjà le travail.
- La rotation doit être **atomique** (transaction + verrou `lockForUpdate()` sur la ligne du refresh token) pour éviter qu'une double requête simultanée émette deux couples valides.
- **Identité stable de l'appareil :** l'`id` exposé par `GET /auth/devices` ne doit **pas changer** après un refresh, et un refresh ne doit **pas** créer de doublon dans la liste. Si l'audit montre que l'appareil est l'`id` de `personal_access_tokens`, introduis un identifiant d'appareil stable (par exemple le `family_id`, ou une table `device_sessions`) et utilise-le dans `/devices`. Choisis la solution la plus simple compatible avec le schéma existant et justifie-la dans le rapport.

---

## Section F — Flux OTP cohérents

Applique à **tous** les flux OTP (inscription, connexion, mot de passe oublié, changement d'email) :

1. Un seul code actif par couple (email, `purpose`). Un renvoi **invalide** le code précédent.
2. Les `purpose` sont **isolés** : un code d'un flux ne valide jamais un autre flux (ajoute-le avec un test si ce n'est pas déjà le cas).
3. Comparaison avec `hash_equals`. Après `otp_max_attempts` essais faux, le code est invalidé (`otp_attempts_exceeded`). Un code utilisé avec succès est consommé (non rejouable).
4. Toutes les réponses d'envoi et de renvoi (`send-code`, `resend-code` des trois flux) contiennent :

```json
{
  "message": "...",
  "expires_in": 600,
  "resend_available_in": 60
}
```

Ces valeurs sont constantes (issues de la config), ce qui permet au front d'afficher un compte à rebours sans rien deviner et sans fuite d'information.
5. Un renvoi trop rapide (avant `otp_resend_delay_seconds`) renvoie `429` code `too_many_requests` avec `retry_after` (voir section H).
6. **Correction de message :** la réponse de `register/verify-code` doit confirmer la vérification (par exemple `"Verification code confirmed."`) et non répéter « A verification code has been sent to your email address. ». Idem si un message équivalent est mal recopié ailleurs.
7. Envoie les emails OTP **après la réponse** (`dispatch(...)->afterResponse()` ou file d'attente selon la configuration du projet) pour que le temps de réponse ne dépende pas de l'envoi (voir section G).

---

## Section G — Anti-énumération de comptes

Un attaquant ne doit pas pouvoir déduire si un email possède un compte.

| Endpoint | Règle |
|---|---|
| `POST /auth/login` | Email inconnu et mot de passe incorrect renvoient la **même** réponse (`422`, code `credentials_invalid`, même message). Quand l'utilisateur n'existe pas, exécute quand même un `Hash::check` contre un hash factice pour égaliser le temps de réponse. |
| `POST /auth/forgot-password/send-code` et `resend-code` | Toujours `200` avec le **même corps**, que le compte existe ou non (`"If an account exists for this email, a verification code has been sent."` + `expires_in` + `resend_available_in`). N'envoie le code que si le compte existe. |
| `POST /auth/forgot-password/verify-code` | Pour un email inexistant, la réponse est identique à celle d'un mauvais code (`otp_invalid`). |
| `POST /auth/register/send-code` et `resend-code` | Toujours `200` avec le même corps. Si un compte existe déjà pour cet email, **n'envoie pas de code d'inscription** mais envoie un email informant qu'un compte existe déjà (avec renvoi vers la connexion). Crée la notification/mailable nécessaire. |
| `POST /auth/register/complete` | Si l'email est devenu pris entre-temps (course), renvoie `422` code `verification_token_invalid`, pas un message révélant l'existence du compte. |
| `login/resend-code` | `challenge_token` inconnu ou expiré → `422` code `challenge_token_invalid` (même réponse dans les deux cas). |

Le changement d'email authentifié (`PUT /auth/profile`) peut en revanche signaler qu'un email est déjà utilisé (erreur de validation standard) : l'utilisateur est authentifié.

---

## Section H — Limitation de débit

1. Audite l'existant ; **ne réduis jamais** une limite existante plus stricte.
2. Définis des limiteurs nommés avec `RateLimiter::for(...)` dans `AppServiceProvider` (ou le fournisseur dédié du projet), valeurs lues dans `config('auth_api.rate_limits')` :

| Limiteur | Routes | Clé | Valeur par défaut |
|---|---|---|---|
| `otp-send` | `*/send-code`, `*/resend-code` (les 3 flux) | email normalisé + IP (pour `login/resend-code`, hash du `challenge_token` + IP) | 1/min **et** 5/heure |
| `otp-verify` | `*/verify-code`, `profile/verify-email`, `verify-pending-email` | email ou jeton + IP | 10/min |
| `login` | `POST /auth/login` | email normalisé + IP, puis IP seule | 5/min par email+IP, 20/min par IP |
| `refresh` | `POST /auth/refresh` | IP | 60/min |
| `sensitive` | `PUT /auth/password`, `DELETE /auth/account`, `forgot-password/reset` | utilisateur ou IP | 5/min |

3. Les dépassements renvoient `429` avec le **header `Retry-After`** (secondes) et ce corps :

```json
{ "code": "too_many_requests", "message": "Too many attempts. Please try again later.", "retry_after": 42 }
```

4. Vérifie que `Retry-After` est bien listé dans `exposed_headers` de `config/cors.php` (cf. tâche 01).

---

## Section I — Révocation de sessions lors des changements sensibles

| Événement | Effet |
|---|---|
| `PUT /auth/password` (succès) | Révoque **tous les autres appareils** (access + refresh, `revoked_reason = 'password_changed'`). L'appareil courant reste connecté. La réponse ajoute `"other_sessions_revoked": <nombre>`. |
| `POST /auth/forgot-password/reset` (succès) | Révoque **tous** les tokens de l'utilisateur (aucun token n'est émis : il devra se connecter). |
| `DELETE /auth/devices/{id}` | Révoque l'access token **et** le refresh token de cet appareil. |
| `DELETE /auth/devices` | Révoque tous les appareils **sauf le courant** ; réponse avec `"revoked_count": <nombre>`. |
| `POST /auth/logout` | Révoque l'access token courant **et le refresh token / la famille de cet appareil uniquement** (les autres appareils restent connectés). |

Chaque révocation de refresh token enregistre sa `revoked_reason`.

---

## Section J — Suppression de compte

`DELETE /auth/account` :
- exige le mot de passe dans le corps (`password`, vérifié avec `Hash::check`) ; sinon `422` code `password_current_incorrect` ;
- révoque **tous** les access et refresh tokens (`revoked_reason = 'account_deleted'`), supprime/anonymise selon le mécanisme déjà en place (soft delete ou non : **ne change pas** ce choix, documente-le), nettoie les codes OTP de l'utilisateur ;
- répond `200` avec `{ "message": "Your account has been deleted." }` (ou conserve le message existant s'il y en a un).

---

## Section K — Appareils

`GET /auth/devices` renvoie :

```json
{
  "devices": [
    { "id": 12, "name": "iPhone 15 Test", "is_current": true, "created_at": "2026-10-09T10:00:00Z", "last_used_at": "2026-10-09T10:05:00Z" }
  ]
}
```

- `id` stable (voir section E), `is_current` indique l'appareil de la requête, dates au format ISO 8601.
- Un utilisateur ne peut agir que sur **ses** appareils : `DELETE /auth/devices/{id}` d'un appareil inexistant **ou appartenant à un autre utilisateur** → `404` code `device_not_found` (jamais 403, pour ne pas révéler l'existence).
- Supprimer l'appareil courant est autorisé et équivaut à un logout ; la réponse ajoute `"current_device_revoked": true`.

---

## Section L — Vérifications d'email

- Documente (audit) la différence entre `verify-pending-email` (publique) et `profile/verify-email` (protégée).
- **Évalue la sécurité** de la route publique : peut-on, sans être authentifié, valider le changement d'email d'un autre compte en connaissant seulement un email et un code ? Vérifie qu'elle exige bien un identifiant non devinable en plus du code et qu'elle est soumise au limiteur `otp-verify`.
- Si tu trouves une faille, **corrige-la seulement si le correctif est local et évident** ; sinon signale-la en tête du rapport (priorité haute). N'efface pas la route sans l'avoir demandé.

---

## Section M — Contrat d'erreur avec `code` stable

Toute erreur métier 4xx renvoie du JSON avec **`code`** (snake_case, stable, jamais traduit) en plus de `message`. Le front doit pouvoir réagir en lisant `code` uniquement.

Forme générale :

```json
{ "code": "otp_invalid", "message": "The verification code is invalid." }
```

Les erreurs de validation gardent leur forme Laravel et ajoutent `code` :

```json
{ "code": "validation_failed", "message": "The given data was invalid.", "errors": { "email": ["..."] } }
```

### Catalogue minimal

| `code` | Statut | Situation |
|---|---|---|
| `unauthenticated` | 401 | token absent, invalide, expiré ou révoqué |
| `validation_failed` | 422 | toute erreur de validation de formulaire |
| `credentials_invalid` | 422 | login refusé (email ou mot de passe) |
| `otp_invalid` | 422 | code incorrect (ou email inexistant dans forgot-password) |
| `otp_expired` | 422 | code périmé |
| `otp_attempts_exceeded` | 422 | trop d'essais, code invalidé |
| `verification_token_invalid` | 422 | `verification_token` invalide ou expiré |
| `challenge_token_invalid` | 422 | `challenge_token` invalide ou expiré |
| `reset_token_invalid` | 422 | `reset_token` invalide ou expiré |
| `refresh_token_invalid` | 422 | refresh token inconnu, révoqué ou réutilisé |
| `refresh_token_expired` | 422 | refresh token expiré |
| `password_current_incorrect` | 422 | mot de passe actuel incorrect (changement de mot de passe, suppression de compte) |
| `device_not_found` | 404 | appareil introuvable ou non possédé |
| `resource_not_found` | 404 | tout autre `ModelNotFoundException` / route inconnue |
| `too_many_requests` | 429 | limite de débit (avec `retry_after`) |
| `forbidden` | 403 | action interdite (si utilisée) |

### Implémentation

- Repère comment les erreurs métier sont produites aujourd'hui (`ValidationException::withMessages`, `abort`, `response()->json`, exceptions maison). Introduis une classe d'exception unique (par exemple `App\Exceptions\ApiException(code, message, status, extra)`), ou adapte celle qui existe, et fais-la rendre par `withExceptions` dans `bootstrap/app.php`.
- Ajoute des handlers JSON pour `AuthenticationException` (401 `unauthenticated`), `ModelNotFoundException` / `NotFoundHttpException` (404), `ThrottleRequestsException` (429 + `retry_after`), `ValidationException` (ajout de `code`).
- **Conserve les codes HTTP et les messages actuels** de chaque erreur, sauf ceux explicitement modifiés ci-dessus. Les messages restent en anglais.
- Les erreurs de refresh restent en **422** (décision arrêtée : un `422` sur `/auth/refresh` signifie « session perdue, retour à l'écran de connexion »).

---

## Tests à écrire (Feature)

Au minimum un test par ligne :

**Tokens et utilisateur**
- `register/complete`, `login/verify-code`, `refresh` renvoient `expires_in` et `refresh_expires_in` (entiers positifs) ; l'access token expire réellement (voyage dans le temps avec `travel()` → 401 `unauthenticated`).
- Les trois réponses renvoient le même `user` (mêmes clés) avec `country` et `language` ; plus de `*_source`.
- `verification_token`, `challenge_token`, `reset_token` ont `expires_in`.

**Refresh**
- Rotation nominale ; ancien token → 422 `refresh_token_invalid`.
- **Réutilisation** : refresh A → B, puis A à nouveau → 422 **et** B ne fonctionne plus (famille révoquée) ; un log de sécurité est émis.
- Refresh expiré → `refresh_token_expired`.
- Double appel simultané avec le même refresh : un seul réussit (simule avec deux appels séquentiels si la concurrence est difficile à tester).
- L'`id` d'appareil dans `/devices` est identique avant/après refresh ; pas de doublon.

**OTP**
- Un renvoi invalide l'ancien code ; le code d'inscription n'ouvre pas une connexion ; code rejoué → refusé ; `otp_max_attempts` dépassé → `otp_attempts_exceeded` ; renvoi avant le délai → 429 avec `Retry-After` et `retry_after`.
- Les réponses d'envoi contiennent `expires_in` et `resend_available_in`.
- Message de `register/verify-code` corrigé.

**Anti-énumération**
- `login` : email inconnu et mauvais mot de passe → corps strictement identiques.
- `forgot-password/send-code` : email existant et inexistant → mêmes statut et corps (aucun code envoyé pour l'inexistant).
- `register/send-code` pour un email déjà inscrit → même `200`, **aucun** OTP créé, notification « compte existant » envoyée (utilise `Mail::fake()` / `Notification::fake()`).
- `login/resend-code` : jeton inconnu et jeton expiré → même réponse.

**Limites de débit**
- Dépassement de chaque limiteur nommé → 429 + `Retry-After` + `code`.

**Sessions**
- Changement de mot de passe : l'appareil courant reste valide, les autres reçoivent 401, leurs refresh tokens sont refusés ; `other_sessions_revoked` correct.
- Reset de mot de passe : tous les tokens révoqués.
- `DELETE /devices/{id}` : l'access et le refresh de l'appareil visé sont invalidés ; appareil d'un autre utilisateur → 404 `device_not_found`.
- `DELETE /devices` : tous sauf le courant ; `revoked_count` correct.
- `logout` : seul l'appareil courant est déconnecté (access + refresh).
- Suppression de compte : mot de passe requis ; mauvais mot de passe → `password_current_incorrect` ; tous les tokens révoqués.

**Erreurs**
- Chaque ligne du catalogue §M a au moins un test qui vérifie `code`, statut et présence de `message`.
- Route inconnue sous `/api/v2/...` → 404 JSON avec `resource_not_found`.

---

## Définition de terminé

- [ ] `docs/api/audit_auth_v2.md` livré et complet (toutes les routes de `route:list`).
- [ ] Config centralisée, `.env.example` à jour, plus de durée en dur.
- [ ] `expires_in` / `refresh_expires_in` partout ; access token qui expire réellement.
- [ ] `UserResource` unique et identique dans toutes les réponses.
- [ ] Réutilisation de refresh token détectée et session révoquée ; `id` d'appareil stable.
- [ ] Anti-énumération en place sur login, forgot-password et register.
- [ ] Limiteurs nommés avec `429` + `Retry-After`.
- [ ] Révocations de sessions conformes à la section I ; suppression de compte avec mot de passe.
- [ ] Toutes les erreurs métier 4xx portent un `code` du catalogue.
- [ ] Toute la suite de tests passe ; formatage appliqué.
- [ ] Rapport final au format du `.ai/agent-prompts/00_contexte_commun.md` §5, avec la liste des changements cassants et les éventuelles failles détectées en tête.
