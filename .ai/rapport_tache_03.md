# Rapport final — Tâche 03 : guide d'intégration de l'API v2

## 1. Résumé

Mise à jour de `docs/api/guide_api_v2.md` pour l'aligner sur l'API v2 **réelle après les corrections post-revue C1–C10** (tâche 04). Le catalogue d'erreurs est réduit aux 14 codes réels, les codes de jeton sont unifiés (`invalid_token`), les champs (`reset_token`), les statuts (`403 pending_email_verification_required`, `422 credentials_invalid`, `422 invalid_refresh_token`/`refresh_token_expired`, `422 password_current_incorrect`), les TTL (`expires_in = 86400`) et les limiteurs (`otp-verify` 5/min) sont corrigés. Les fuites d'existence non masquées sont documentées telles quelles. Test de cohérence et suite complète verts.

## 2. Fichiers créés / modifiés / supprimés

Modifiés
- `docs/api/guide_api_v2.md` — alignement sur l'API v2 réelle (voir §5).
- `.ai/changelog.md` — entrée « Tâche 03 : mise à jour du guide d'intégration API v2 ».

Créés
- `.ai/rapport_tache_03.md` (ce rapport).

Supprimés
- Aucun.

## 3. Décisions prises

- **Catalogue d'erreurs** : reconstruit à partir de `lang/en/auth.php` + `lang/en/validation.php` + handlers réels ; retrait des codes inexistants (`verification_token_invalid`, `challenge_token_invalid`, `reset_token_invalid`, `refresh_token_invalid`) et ajout de `pending_email_verification_required`, `password_current_incorrect`.
- **`invalid_token` unifié** : documenté comme un seul code couvrant `verification_token`, `challenge_token` et `reset_token`, le champ concerné étant distingué par `errors`.
- **Fuites d'existence** (`unique`/`exists`) : documentées explicitement (`register/send-code`, `forgot-password/send-code`, `login/resend-code`) plutôt que masquées, car elles sont réelles et non corrigées (hors périmètre C). L'ancienne promesse « anti-énumération » a été retirée.
- **`login` `403`** : message retenu égal à celui de `lang/en/auth.php` (`pending_email_verification_required`), corps complété par `email`/`pending_email` conformément au contrôleur.
- **`expires_in`** access token : ramené à `86400` partout (en-têtes login/refresh, exemple d'intercepteur, rafraîchissement proactif ≈ 19 h).
- **§11** : ajout d'un bloc « Corrections récentes (C1–C10) » agrégeant les changements cassants des rapports 02 et 04, en plus du tableau historique existant.

## 4. Écarts avec le prompt

- La tâche 03 avait déjà produit le guide (entrée changelog du 2026-10-09). Cette exécution est une **mise à jour d'alignement** post-corrections, et non une réécriture complète : la structure imposée (sections 0 à 11) et le test de cohérence étaient déjà en place.
- Aucun endpoint ni route ajouté/retiré ; seule la documentation a été corrigée. Aucun fichier PHP/OpenAPI touché.
- Pas de marqueur `⚠️ À confirmer` ajouté : toutes les valeurs corrigées sont prouvées par le code ou la config.

## 5. Changements cassants documentés (ancien → nouveau dans le guide)

| # | Élément | Ancien (guide) | Nouveau (guide) |
|---|---|---|---|
| C1 | Codes d'erreur 4xx | `{message}` (+`errors`) | `{code, message}` (+`errors`), `code` stable |
| C1 | Jeton intermédiaire invalide | 3 codes distincts `*_token_invalid` | `invalid_token` unique |
| C2 | Access token | 3600 s | `86400` s (1440 min) |
| C2 | `login` email en attente | `403` sans code (N/A) | `403 pending_email_verification_required` |
| C3 | `otp-verify` | 10 req/min | 5 req/min |
| C4 | `login` identifiants invalides | `422 validation_failed` | `422 credentials_invalid` |
| C4 | `forgot-password/reset` | champ `token` | champ `reset_token` |
| C4 | `refresh` échec | `422 validation_failed` | `422 invalid_refresh_token` / `refresh_token_expired` |
| C5 | `PUT /auth/profile` | `country_id`, `language_id` acceptés | champs retirés → `PATCH /locale` |
| C5 | `DELETE /account` mauvais mdp | `422 validation_failed` | `422 password_current_incorrect` |
| C6 | `GET /locale` | `source: none` possible | `country`/`language` `null` explicites ; `source ∈ {profile, single_active}` |
| C7 | `login/resend-code` | `challenge_token` ou `email` | `challenge_token` obligatoire |

## 6. Résultats des tests

- Commande ciblée : `php artisan test tests/Feature/Docs/ApiGuideCoversAllRoutesTest.php --compact --no-ansi` → **1 test, 4 assertions, vert**.
- Commande complète : `php artisan test --compact --no-ansi` → **80 tests, 600 assertions, 0 échec**.
- Formatage : aucun fichier PHP modifié → `vendor/bin/pint` non requis.
- **Note (test instable)** : un premier passage complet a échoué sur `AuthTokenExpirationTest::the access token expires_in reflects the real token expiration` (« Failed asserting that 1.053685 is equal to 1 or is less than 1 »). La tolérance de 1 s du test est mise en défaut par une requête dépassant 1 s. Le test passe en isolation et au second passage complet. Aucune modification du test (hors périmètre).

## 7. Observations hors périmètre

- **Fuites d'existence** : `RegisterSendCodeRequest` (`unique:users,email`), `ForgotPasswordSendCodeRequest` (`exists:users,email`) et `login/resend-code` révèlent l'existence d'un compte via un 422 de validation distinct. Non corrigées (hors périmètre C) ; documentées dans le guide (§4.1, §4.3, §4.4, §10).
- **Traductions manquantes** : `lang/fr/` sans `validation.php`, `passwords.php`, `pagination.php` ; clé `verification_code_confirmed` absente de `lang/fr/auth.php`.
- **Test instable** : `AuthTokenExpirationTest` (tolérance 1 s trop stricte).
- `verify-pending-email` reste publique (risque d'interception d'email, noté par l'audit).
- `notes/auth_v2_summary.md` et les scripts de développement non touchés.

## 8. Points à confirmer par un humain

- Champs OpenAPI `contact`/`license` : `support@example.com` et URL de licence génériques à renseigner (C8#6).
- Anti-énumération sur `register/send-code` et `forgot-password/send-code` : à corriger lors d'une tâche dédiée si souhaité (hors périmètre C).
- Ajouter `lang/fr/validation.php`, `passwords.php`, `pagination.php` et la clé `verification_code_confirmed` si le support complet du français est requis.
- Élargir la tolérance de `AuthTokenExpirationTest` (> 1 s) pour supprimer le test instable.
