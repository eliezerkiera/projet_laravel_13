# 03 — Tâche : réécriture du guide de l'API v2 pour les développeurs front/mobile

> **Avant de commencer, lis `00_contexte_commun.md` en entier.** Les règles qui y figurent s'appliquent ici.

## Objectif

Produire **un guide Markdown unique, fiable, complet et facile à suivre**, destiné à des **développeurs front web et mobile** qui intègrent l'API v2, et qui sert aussi de procédure de test manuel (Insomnia / Postman).

**Prérequis :** les tâches 01, 02, 04 et 05 sont terminées, leurs tests passent, et `docs/api/audit_auth_v2.md` ainsi que `openapi.yaml` (à la racine du projet) existent.

**Entrées :**
- l'ancien guide `test_auth_v2_final.md` (le fichier doit se trouver dans le dépôt, par exemple dans `docs/api/` ; s'il est absent, demande-le) ;
- `docs/api/audit_auth_v2.md` ;
- `openapi.yaml` (à la racine du projet) : la **référence machine** des endpoints (schémas, codes d'erreur, limites), déjà produite et corrigée ;
- le **code réel** et `php artisan route:list --path=api/v2 --json`.

**Sortie :**
- `docs/api/guide_api_v2.md` (le nouveau guide) ;
- l'ancien guide déplacé dans `docs/api/archive/` ;
- un test de cohérence (voir fin du document).

Si `notes/auth_v2_summary.md` ou des collections Insomnia existent dans le dépôt, **ne les modifie pas** : signale simplement dans ton rapport qu'elles sont à mettre à jour ou à archiver.

---

## Règle d'or : vérité avant tout

1. **Aucune information inventée.** Chaque exemple de réponse doit provenir d'une **exécution réelle** : lance l'application avec une base de test seedée, appelle les endpoints (curl, ou un test Feature temporaire qui affiche la réponse JSON), et recopie la sortie réelle (tokens tronqués : `"1|abc..."`).
2. Si tu ne peux pas exécuter ou vérifier une information, écris à cet endroit **`⚠️ À confirmer : <ce qui manque>`** et liste toutes ces mentions dans le rapport.
3. L'ancien guide est **une source d'idées, pas une source de vérité**. En cas de désaccord avec le code, c'est le code qui a raison. Corrige l'ancien contenu et signale l'écart dans ton rapport.
4. Les valeurs de durée (OTP, tokens) proviennent de la config réelle, pas d'estimations.

---

## Répartition des rôles : OpenAPI et guide

- L'**OpenAPI** est la référence exhaustive et outillable de chaque endpoint (schémas, énumération des `code`, limites). **Ne la recopie pas en entier** dans le guide.
- Le **guide** apporte ce que l'OpenAPI ne dit pas : le parcours, les concepts, l'ordre des appels, la gestion des tokens côté client, les exemples d'intégration, les pièges, les tests manuels.
- Les deux doivent rester **cohérents** : mêmes noms de champs, mêmes `code`, mêmes limites, mêmes durées. En cas d'écart constaté, corrige le bon des deux, et signale-le dans ton rapport.
- Dans l'en-tête du guide, renvoie vers `openapi.yaml` (à la racine du projet) pour la référence complète. Les sections de flux ne montrent que les requêtes et réponses utiles à la compréhension (extraits **réels**, tokens tronqués).

---

## Étape 0 — Compléter l'OpenAPI (petits manques constatés à la revue)

Avant de rédiger le guide, dans `openapi.yaml` (à la racine du projet), sans rien inventer (exemples issus d'exécutions réelles) :

1. Ajoute un exemple nommé pour les codes d'erreur qui n'en ont aucun : `otp_expired`, `otp_max_attempts`, `refresh_token_expired`.
2. `resource_not_found` figure dans l'énumération mais aucun endpoint ne le renvoie en exemple : vérifie le comportement réel d'une route inconnue sous `/api/v2/...` et décris-le avec un exemple dans la description générale de l'API (section sur les erreurs). Si ce code n'est jamais renvoyé, retire-le de l'énumération.
3. Ajoute un exemple réel aux réponses `200` qui n'en ont pas : `register/send-code`, `register/resend-code`, `register/verify-code`, `login`, `login/resend-code`, `forgot-password/send-code`, `forgot-password/resend-code`, `forgot-password/verify-code`, `forgot-password/reset`, `verify-pending-email`, `GET /auth/me`, `PUT /auth/profile`, `profile/verify-email`, `DELETE /auth/account`, `POST /auth/logout`, `GET /countries/active`.
4. Relance le linter (`npx --yes @redocly/cli lint openapi.yaml`) et le test de couverture routes/OpenAPI ; les deux doivent rester verts.

---

## Style et forme

- Français clair et direct, phrases courtes, pas de ton commercial, **pas de « résumé final » promotionnel**.
- Les noms de champs, d'endpoints, de headers, de codes d'erreur et les messages d'API restent en **anglais**, en `code`.
- **Un seul sommaire cliquable** en tête.
- **Un seul modèle par endpoint** (voir plus bas), appliqué partout sans variation.
- Utilise des **tableaux** pour tout ce qui est énumérable (endpoints, tokens, codes d'erreur, priorités), des **blocs de code** pour requêtes et réponses, des **diagrammes Mermaid** (rendus par GitHub/GitLab) pour les flux.
- Pas de doublon : une seule checklist de validation, une seule table des endpoints.
- Corrige l'orthographe et les fautes d'accord de l'ancien guide.

---

## Structure imposée du guide

### 0. En-tête
Titre, version de l'API, date de mise à jour, à qui s'adresse le guide, **comment l'utiliser** (lecture rapide pour intégrer ; section Tests pour valider).

### 1. Démarrage rapide (5 minutes)
Le chemin le plus court pour un premier appel authentifié : variables d'environnement, `GET /locale`, inscription ou connexion, `GET /auth/me`. Une seule suite d'étapes numérotées, avec le minimum d'explications.

### 2. Concepts à connaître
- **2.1 Headers** : tableau des headers de requête (`Accept: application/json` **obligatoire** et pourquoi, `Content-Type`, `Authorization`, `X-Locale-Override`, `Accept-Language`) et de réponse (`Content-Language`, `Retry-After`, `Vary`).
- **2.2 Les 5 jetons** : tableau `verification_token`, `challenge_token`, `reset_token`, `access_token`, `refresh_token` avec colonnes : obtenu à quel endpoint, sert à quel endpoint, durée de vie (valeur réelle), à stocker ou non. Ajoute un encadré **« Ne pas confondre »**.
- **2.3 Format des erreurs** : forme JSON, rôle du champ `code`, règle « le front se base sur `code`, jamais sur `message` ». Renvoie vers le catalogue de la section 9.
- **2.4 Format des dates, des identifiants, de l'unité des durées** (`expires_in` en secondes).

### 3. Langue et pays (section la plus importante pour le front)
Cette section doit rendre le comportement **évident** :
- **3.1 Le principe en une phrase** : « Le back dit toujours quelle langue et quel pays il applique ; le front ne devine rien. »
- **3.2 La règle d'or pour le front** : tableau « Moment → Action » :

| Moment | Action du front |
|---|---|
| Démarrage de l'app | `GET /api/v2/locale`, stocker `language.code` et `country`, afficher le sélecteur de pays si `country_selection_required` |
| Chaque requête suivante | Envoyer `X-Locale-Override: <code stocké>` |
| L'utilisateur change de langue | Mettre à jour le stockage local ; s'il est connecté, `PATCH /api/v2/locale` |
| Après login / logout | Refaire `GET /api/v2/locale` |

  Explique **pourquoi** envoyer systématiquement l'override (identique pour anonyme et connecté, indépendant de l'ordre des middlewares) et que le header n'est jamais persisté.
- **3.3 Priorité de résolution** : tableau des 5 niveaux avec la valeur de `source` correspondante, et la règle « une valeur inconnue est ignorée, jamais d'erreur ».
- **3.4 Le pays** : règles de résolution, `country_selection_required` (tableau des 5 situations), pas de détection par IP, aucun header de pays.
- **3.5 Référence de `GET /locale` et `PATCH /locale`** (au modèle d'endpoint), avec **tous les cas du `PATCH`** : langue seule, pays seul, `null` pour remettre en automatique un champ, `reset_to_auto`, combinaisons interdites.
- **3.6 Ce que la langue change réellement** : d'après l'audit i18n (rapports des tâches 01, 04 C10 et 05 R5). L'audit de départ a établi que les messages d'authentification sont **traduits** (`lang/fr` et `lang/en`). Dis la vérité sur ce qui est traduit et ce qui ne l'est pas (messages de validation, emails OTP, noms de pays et de langues), signale les clés manquantes, et rappelle que **`code` n'est jamais traduit** : c'est lui que le front doit tester.
- **3.7 Exemple d'intégration** (TypeScript, court) : initialisation au démarrage, intercepteur qui ajoute `X-Locale-Override`, changement de langue.
- **3.8 Pièges courants** : tableau court (ex. « j'ai envoyé `Accept-Language` après avoir changé de langue et rien ne change » → utilise `X-Locale-Override` ; « le pays est `null` » → `country_selection_required`).

### 4. Flux d'authentification
Pour chaque flux : **un diagramme Mermaid de séquence** (client / API / email), un **tableau des étapes**, puis les endpoints au modèle.
- 4.1 Inscription (`register/send-code` → `verify-code` → `complete`)
- 4.2 Connexion avec 2FA (`login` → `login/verify-code`)
- 4.3 Mot de passe oublié (`forgot-password/send-code` → `verify-code` → `reset`) : **flux entier, avec le `reset_token`**
- 4.4 Renvoi de code (`resend-code` des trois flux) : délais, `resend_available_in`, comportement UX recommandé (compte à rebours)
- 4.5 Rafraîchissement et cycle de vie des tokens (voir section 5)

### 5. Gestion des tokens côté client
- Où stocker (Keychain / Keystore sur mobile ; cookie `HttpOnly` ou mémoire sur le web : explique le compromis, sans prescrire une architecture que le back n'implémente pas).
- Quand rafraîchir : sur `401`/`unauthenticated` **et** proactivement avant `expires_in`.
- **Piège de la rotation** : deux refresh simultanés avec le même token provoquent une **détection de réutilisation et la fermeture de la session**. Fournis un exemple TypeScript de **file d'attente à un seul refresh en vol** (« single-flight ») que toutes les requêtes en attente partagent, avec rejeu de la requête initiale.
- Que faire si `/refresh` échoue (`422` : `refresh_token_invalid` ou `refresh_token_expired`) : vider les tokens, retour à l'écran de connexion. Précise que c'est un **422 et non un 401**, et pourquoi il ne faut pas relancer l'intercepteur de refresh sur cette route.

### 6. Profil, mot de passe, email, appareils, déconnexion
Tous les endpoints protégés de `routes/api.php`, y compris `PUT /profile` (il ne modifie plus la langue ni le pays : c'est le rôle exclusif de `PATCH /locale`), `profile/verify-email`, `verify-pending-email` (explique la différence avec `profile/verify-email` et quand l'utiliser), `PUT /password` (effet : autres appareils déconnectés), `DELETE /account` (mot de passe requis), `devices` (format, `is_current`, `id` stable), `logout` (ce qui est révoqué exactement).

### 7. Référence des endpoints
**Un seul tableau** de tous les endpoints : méthode, chemin complet, auth requise, limiteur de débit, corps, code de succès, lien vers la section détaillée. Il doit correspondre exactement à `route:list` **et** aux chemins de `openapi.yaml` (à la racine du projet) (ajoute une colonne « OpenAPI » avec le tag de l'endpoint).

### 8. Limites de débit
Tableau des limiteurs (valeurs réelles), comportement `429`, `Retry-After`, recommandation front (désactiver le bouton et afficher le compte à rebours).

### 9. Catalogue des erreurs
Un tableau unique : `code` | statut HTTP | message exact | quand | **action recommandée pour le front**. Il doit reprendre **exactement** l'énumération `code` de l'OpenAPI, noms compris (`otp_max_attempts`, `invalid_refresh_token`, `invalid_token`, `device_not_found`, etc.), telle que fixée par `04_tache_corrections.md` C1 et `05_corrections_residuelles.md` R2. Les noms du catalogue de la tâche 02 §M qui diffèrent sont **abandonnés**. Ajoute un exemple de réponse de validation (`422` avec `errors`).

### 10. Tests manuels (Insomnia / Postman)
- **10.1 Environnement** : variables et valeurs réelles (reprendre `base_url`, `auth_url`, tokens, etc. ; supprime les variables inutilisées, ajoute `locale_url` si utile).
- **10.2 Enchaînement automatique des tokens** : comment remplir `access_token`, `refresh_token`, `challenge_token`, `verification_token`, `reset_token` **automatiquement** depuis la réponse (chaînage de réponse Insomnia, et script « Tests » Postman en JavaScript) pour supprimer les copier-coller manuels.
- **10.3 Où trouver les codes OTP en local** (logs, table `verification_codes`) avec la commande exacte pour les lire.
- **10.4 Parcours de test dans l'ordre**, un tableau « # | requête | résultat attendu » qui remplace l'ancienne « collection minimale ».
- **10.5 Checklist de validation finale** : **une seule** liste de cases à cocher, couvrant : langue/pays (tous les scénarios du `GET /locale`), inscription, connexion 2FA, forgot-password, refresh et réutilisation, limites de débit, anti-énumération (mêmes réponses pour email existant/inexistant), révocation de sessions, suppression de compte.
- **10.6 Réinitialiser les données** entre deux scénarios (commandes exactes).

### 11. Annexe : changements par rapport à l'ancienne version
Tableau des **changements cassants** repris des rapports des tâches 01, 02, 04 et 05 (route déplacée, champs retirés, nouveaux champs, nouveaux codes), pour les équipes qui connaissaient l'ancien guide.

---

## Modèle unique pour chaque endpoint

````markdown
#### `METHOD /api/v2/chemin`

**Rôle :** une phrase.
**Auth :** publique | Bearer requis · **Limite :** `nom-du-limiteur` (valeur)

**Requête**
```http
...headers utiles...
```
```json
{ "...": "corps réel" }
```

| Champ | Type | Obligatoire | Règles |
|---|---|---|---|

**Succès — `200 OK`**
```json
{ "...": "réponse réelle (tokens tronqués)" }
```

**Erreurs possibles**
| Statut | `code` | Quand |
|---|---|---|

**À retenir :** (facultatif) 1 à 3 lignes, y compris l'effet sur les autres sessions, le compte à rebours, etc.
````

---

## Contrôle de cohérence automatisé (livrable)

Écris un test Feature (par exemple `tests/Feature/Docs/ApiGuideCoversAllRoutesTest`) qui :
1. liste toutes les routes `api/v2` via `Route::getRoutes()` (ignore les routes de fallback ou d'outillage éventuelles, en documentant l'exception) ;
2. lit `docs/api/guide_api_v2.md` ;
3. échoue si une route (méthode + chemin) **n'apparaît pas** dans le tableau de la section 7, avec un message qui nomme les routes manquantes.

Objectif : empêcher que le guide dérive du code.

---

## Définition de terminé

- [ ] `docs/api/guide_api_v2.md` conforme à la structure imposée (sections 0 à 11), un seul sommaire, un seul modèle d'endpoint.
- [ ] Étape 0 réalisée : tous les codes d'erreur et toutes les réponses `200` de l'OpenAPI ont un exemple réel ; linter et test de couverture verts.
- [ ] Le guide est cohérent avec `openapi.yaml` (noms de champs, `code`, limites, durées) ; tout écart est corrigé et signalé.
- [ ] Tous les endpoints de `route:list` documentés, avec requêtes/réponses **réellement exécutées**.
- [ ] Les flux inscription, connexion, **forgot-password complet**, refresh et réutilisation ont chacun un diagramme Mermaid.
- [ ] La section 3 (langue/pays) est autonome : un développeur front qui ne lit que cette section sait quoi faire.
- [ ] Catalogue d'erreurs complet avec action recommandée.
- [ ] Exemples TypeScript (initialisation de la locale, refresh single-flight) fonctionnellement corrects et cohérents avec l'API réelle.
- [ ] Chaînage automatique des tokens documenté (Insomnia et Postman).
- [ ] Aucune mention `⚠️ À confirmer` non listée dans le rapport.
- [ ] Ancien guide archivé ; test de cohérence documentation/routes en place et vert ; toute la suite de tests passe.
- [ ] Rapport final au format du `00_contexte_commun.md` §5, avec la liste des `⚠️ À confirmer` et des écarts constatés entre l'ancien guide et le code.