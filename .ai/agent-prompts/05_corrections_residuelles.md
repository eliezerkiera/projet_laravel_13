# 05 — Tâche : corrections résiduelles (à exécuter avant la tâche 03)

> **Avant de commencer, lis `.ai/agent-prompts/00_contexte_commun.md` en entier.** Les règles qui y figurent s'appliquent ici.

## Contexte

La tâche 04 est terminée (rapport : 80 tests, 600 assertions, OpenAPI validée par Redocly). Le tableau de clôture de l'audit montre 14 problèmes sur 15 corrigés. Il reste :

- le problème **#6 (anti-énumération), partiellement corrigé** : le rapport de la tâche 04 précise que `RegisterSendCodeRequest` (`unique:users,email`) et `ForgotPasswordSendCodeRequest` (`exists:users,email`) renvoient un `422` de validation différent pour un email connu et inconnu ;
- quelques écarts de l'OpenAPI et de la configuration de langue relevés à la revue.

Comme d'habitude : **vérifie d'abord dans le code** si c'est le code ou la documentation qui diverge, corrige le bon des deux, et dis-le dans le rapport.

---

## R1 — Fermer les fuites d'existence de compte (priorité haute)

**Constat.**
- `POST /auth/register/send-code` et `resend-code` : la règle `unique:users,email` renvoie un `422` pour un email déjà inscrit, alors que l'OpenAPI annonce « même réponse 200 si le compte existe déjà ». Documentation et code se contredisent.
- `POST /auth/forgot-password/send-code` et `resend-code` : la règle `exists:users,email` renvoie un `422` pour un email inconnu, alors que l'OpenAPI annonce « réponse identique que le compte existe ou non ».

Un attaquant peut donc sonder quels emails ont un compte.

**À faire.**
1. Dans `RegisterSendCodeRequest` : retire `unique:users,email`. Garde seulement les règles de **format** (`required`, `email`).
2. Dans `ForgotPasswordSendCodeRequest` : retire `exists:users,email`. Garde seulement les règles de format.
3. Dans les contrôleurs, applique exactement le comportement décidé en tâche 02 §G :
   - `register/send-code` et `resend-code` : toujours `200` avec le même corps (`message`, `email`, `expires_in`, `resend_available_in`). Si le compte existe : **aucun** OTP d'inscription, mais un email « un compte existe déjà » envoyé après la réponse. Sinon : OTP envoyé.
   - `forgot-password/send-code` et `resend-code` : toujours `200` avec le même corps. Aucun code créé et aucun email envoyé si le compte n'existe pas.
4. **Aucune différence observable** entre les deux cas : statut, clés JSON, valeurs constantes, et (autant que possible) temps de réponse (l'envoi doit rester différé, `afterResponse()`).
5. `register/complete` : si l'email est devenu pris entre-temps, renvoie `422` `invalid_token`, sans révéler l'existence du compte. Vérifie aussi qu'une contrainte d'unicité en base protège contre une course.
6. `forgot-password/verify-code` pour un email inconnu : même `422` `otp_invalid` qu'un mauvais code.
7. **Contrôle du `403` de `login`** (`pending_email_verification_required`, qui expose `email` et `pending_email`) : il ne doit être renvoyé qu'**après** vérification du mot de passe. Si un attaquant peut le déclencher sans connaître le mot de passe, c'est une fuite : corrige l'ordre des vérifications.
8. Passe en revue toute autre route publique qui pourrait révéler l'existence d'un compte (`login/*`, `verify-pending-email`, `refresh`) et signale le résultat.
9. Corrige les descriptions `422` de l'OpenAPI de ces routes (plus de mention « déjà utilisé » ni « email inexistant »).

**Tests.**
- Pour chacune des quatre routes d'envoi : email existant et email inexistant donnent le **même statut et le même corps** (`assertExactJson` ou comparaison de structure).
- Aucun OTP `register` n'est créé pour un email déjà inscrit ; la notification « compte existant » est envoyée (`Notification::fake()`).
- Aucun OTP `password_reset` n'est créé et rien n'est envoyé pour un email inconnu.
- Le `403` de login n'est jamais renvoyé avec un mauvais mot de passe.
- Un `422` de **format** (email absent ou invalide) reste renvoyé normalement.

---

## R2 — `device_not_found` non documenté

**Constat.** `device_not_found` figure dans l'énumération des codes de l'OpenAPI, mais `DELETE /auth/devices/{id}` documente son `404` avec le schéma partagé `resource_not_found`.

**À faire.**
1. Vérifie le code réel renvoyé pour un appareil inexistant et pour un appareil appartenant à un autre utilisateur.
2. Les deux cas doivent renvoyer `404` avec `code: device_not_found` (jamais `403`).
3. Documente ce `404` avec une réponse **propre à l'endpoint** (exemple `device_not_found`). `resource_not_found` reste pour les autres 404.
4. Si ce n'est pas déjà couvert par `AuthErrorCodesTest`, ajoute un test pour les deux cas.

---

## R3 — Limiteur de `profile/verify-email`

**Constat.** `POST /auth/profile/verify-email` (vérification d'un code OTP) est limité par `auth-api` (60/min), alors que toutes les autres vérifications OTP utilisent `otp-verify` (5/min). La spécification de la tâche 02 §H prévoyait `otp-verify` pour cette route.

**À faire.** Applique `otp-verify` (clé : identifiant utilisateur + IP). Mets à jour l'OpenAPI et ajoute un test de dépassement (`429`, header `Retry-After`, `code: too_many_requests`).

---

## R4 — Exemples de l'OpenAPI

1. **Exemples de `user`** : les exemples des réponses de token (`register/complete`, `login/verify-code`, `refresh`) n'affichent qu'un sous-ensemble du `User`. Remplace-les par des **réponses réelles complètes** (avec `language`, `country`, `language_id`, `country_id`, dates), tokens tronqués.
2. **Exemples `200` manquants** : la plupart des réponses `200` n'ont pas d'exemple. Ajoute des exemples réels pour : les trois endpoints de token, `GET /locale` (exemples nommés : anonyme avec un seul pays actif ; anonyme avec plusieurs pays actifs donc `country: null` et `country_selection_required: true` ; connecté avec préférences ; connecté avec pays devenu inactif), `PATCH /locale`, `GET /auth/devices`, `PUT /auth/password`, `DELETE /auth/devices`, `DELETE /auth/devices/{id}`.
3. **`LocaleContext`** : `language` et `country` sont nullables mais absents de `required`. Si le JSON réel contient **toujours** ces deux clés (valeur `null` le cas échéant), ajoute-les à `required`. Sinon corrige le code pour qu'elles soient toujours présentes.
4. Le test `OpenApiSamplesTest` doit couvrir ces nouveaux exemples (il compare déjà forme documentée et réponse réelle).

---

## R5 — Traductions françaises et retrait de l'arabe — DÉCISION

**Constat (rapport tâche 04, C10).**
- `lang/fr/` n'a pas `validation.php`, `passwords.php`, `pagination.php`, et la clé `verification_code_confirmed` manque dans `lang/fr/auth.php`. Avec `X-Locale-Override: fr`, les erreurs de validation restent donc **en anglais**.
- La table `languages` contient `fr`, `en` et `ar`, mais il n'existe aucun dossier `lang/ar/`. Un utilisateur qui choisit l'arabe recevrait `Content-Language: ar` alors que les messages retombent sur la langue de repli. **Décision humaine : l'arabe n'est pas supporté, il doit être retiré.**

**À faire.**
1. **Français (par défaut : à faire).** Crée `lang/fr/validation.php`, `lang/fr/passwords.php`, `lang/fr/pagination.php` en reprenant **exactement la structure de clés** des fichiers `lang/en/` correspondants, avec des traductions françaises standard. Ajoute `verification_code_confirmed` à `lang/fr/auth.php`. N'ajoute **aucune dépendance** (pas de package de traduction). Écris aussi les `attributes` utiles (`email`, `password`, `code`, `first_name`, `last_name`, etc.).
2. **Retire l'arabe** (décision humaine arrêtée : seules `fr` et `en` sont supportées). Ne traduis rien en arabe.
   1. Recense toutes les occurrences de `ar` comme langue : seeders, factories, migrations, config, `lang/`, tests, `openapi.yaml` (la description générale et le paramètre `X-Locale-Override` en citent). Liste-les dans le rapport.
   2. Vérifie ce qui référence la langue `ar` : `users.language_id` et `countries.language_id` (et toute autre clé étrangère vers `languages`). **Si un pays utilise `ar` comme langue, arrête-toi et signale-le** : ne choisis pas toi-même une langue de remplacement.
   3. Crée une **nouvelle migration** (migration de données) qui : remet à `null` le `language_id` des utilisateurs ayant `ar` (retour en mode automatique), puis **désactive** la langue `ar` si la table `languages` a une colonne d'activation, **sinon supprime** la ligne. `down()` doit remettre l'état précédent autant que possible (réactiver ou recréer la ligne `ar`).
   4. Retire `ar` des seeders, des factories et des tests qui en dépendent.
   5. Retire `ar` des exemples et descriptions de l'OpenAPI (garde `fr` et `en`).
   6. Relève et note dans le rapport la valeur réelle de `config('app.fallback_locale')`.
   7. Tests : `GET /locale` ne liste que `fr` et `en` dans `supported_languages` ; `X-Locale-Override: ar` est **ignoré** (passage à la source suivante, aucune erreur) ; `PATCH /locale` avec l'ancien identifiant de `ar` renvoie `422` « The selected language is invalid. » ; un utilisateur qui avait `ar` se retrouve avec `language_id = null` après la migration.
3. **Test** : avec `X-Locale-Override: fr`, une erreur de validation (par exemple email absent) sort en français, `Content-Language: fr`, et `code` reste `validation_failed`. Un test de parité vérifie que `lang/fr/` et `lang/en/` ont les **mêmes clés** (fichier par fichier), pour éviter que l'écart revienne.

---

## R6 — À ne PAS modifier

- `info.contact.email` et `info.license.url` (valeurs d'exemple) : liste-les dans « Points à confirmer par un humain ».
- Aucune autre langue n'est ajoutée ou retirée : seul l'arabe est concerné.
- Les durées et limites actuelles (access 86400 s, refresh 2592000 s, `otp-send` 1/min et 5/h, `otp-verify` 5/min, `login` 5/min et 20/min/IP, `refresh` 60/min, `sensitive` 5/min, `auth-api` 60/min), sauf la correction de R3.
- `verify-pending-email` reste publique avec la recherche restreinte à `pending_email` (décision déjà prise).

---

## Définition de terminé

- [ ] Les quatre routes d'envoi (`register/*`, `forgot-password/*`) renvoient des réponses indiscernables selon l'existence du compte ; le `403` de login n'apparaît qu'après le mot de passe ; tests verts.
- [ ] `DELETE /auth/devices/{id}` : `device_not_found` documenté et testé.
- [ ] `profile/verify-email` limité par `otp-verify`, avec test.
- [ ] Exemples de l'OpenAPI complets et réels ; `LocaleContext.required` cohérent ; `OpenApiSamplesTest` à jour.
- [ ] Fichiers `lang/fr/*` complets, clé manquante ajoutée, test de parité des clés `fr`/`en`, arabe retiré (migration de données, seeders, OpenAPI) et testé.
- [ ] Linter OpenAPI relancé (`npx --yes @redocly/cli lint …`), `OpenApiRoutesCoverageTest` toujours vert, toute la suite de tests passe, formatage appliqué.
- [ ] Rapport final au format du `.ai/agent-prompts/00_contexte_commun.md` §5, avec un tableau « problème #6 de l'audit : avant / après ».