# À faire plus tard

L'API fonctionne et rien n'est en production. Les points ci-dessous ont été reportés volontairement.

## Avant la mise en production (obligatoire)

- [ ] **Fermer la fuite d'existence de compte** sur `POST /auth/register/send-code` et `POST /auth/forgot-password/send-code` (et leurs `resend-code`). Les règles `unique:users,email` et `exists:users,email` des FormRequest renvoient un `422` différent selon que l'email a un compte ou non. → Prompt prêt : `05_corrections_residuelles.md`, section R1.
- [ ] Vérifier que le `403` de `login` (`pending_email_verification_required`) n'est renvoyé qu'**après** la vérification du mot de passe. → Même prompt, R1 point 7.
- [ ] Lancer `php artisan migrate:fresh --seed` puis `php artisan test` sur la version finale : les deux doivent passer.
- [ ] Faire relire par un humain les décisions du `README.md` (en particulier : access token à 1 jour, arabe retiré, route `PATCH /locale` déplacée).
- [ ] Remplacer `contact.email` et `license.url` dans `openapi.yaml` (valeurs d'exemple actuellement).

## Quand tu auras le temps

- [ ] **Guide d'intégration Markdown** pour les développeurs front/mobile : démarrage rapide, diagrammes des flux, gestion des tokens côté client (refresh à un seul appel en vol), exemples TypeScript, parcours de test Insomnia/Postman, checklist unique. → `03_tache_documentation.md`.
- [ ] Compléter `openapi.yaml` : exemple pour `resource_not_found` (ou le retirer de l'énumération s'il n'est jamais renvoyé) et exemples réels pour les 16 réponses `200` simples (`send-code`, `login`, `GET /auth/me`, `PUT /auth/profile`, `GET /countries/active`, etc.). → `03_tache_documentation.md`, étape 0.
- [ ] Traductions françaises : ajouter `lang/fr/validation.php`, `passwords.php`, `pagination.php` et la clé `verification_code_confirmed` dans `lang/fr/auth.php`. Aujourd'hui les erreurs de validation restent en anglais même avec `fr`. → `05_corrections_residuelles.md`, R5.
- [ ] Retirer la langue `ar` de la table `languages` (aucune traduction n'existe) si ce n'est pas déjà fait. → `05_corrections_residuelles.md`, R5 point 2.
- [ ] Appliquer le limiteur `otp-verify` (5/min) à `POST /auth/profile/verify-email` si ce n'est pas déjà fait. → `05_corrections_residuelles.md`, R3.
- [ ] Documenter `device_not_found` sur `DELETE /auth/devices/{id}` si ce n'est pas déjà fait. → `05_corrections_residuelles.md`, R2.

## Pour reprendre

1. Lis le `README.md` du dossier (ordre d'exécution et décisions prises).
2. Les tâches 01, 02 et 04 sont terminées. Reste à lancer 05, puis 03.
3. Donne à l'agent, une tâche à la fois : « Lis `00_contexte_commun.md` puis exécute `<fichier de la tâche>`. Termine par le rapport demandé. »

## Rappel : changements cassants déjà appliqués

Un front écrit avant ces changements ne fonctionnera plus tel quel. Il doit suivre `openapi.yaml`.

| Avant | Maintenant |
|---|---|
| `PATCH /api/v2/auth/locale` | `PATCH /api/v2/locale` |
| `forgot-password/reset` avec le champ `token` | champ `reset_token` |
| `login/resend-code` avec `challenge_token` ou `email` | `challenge_token` obligatoire |
| `PUT /auth/profile` acceptait `country_id` / `language_id` | champs retirés (utiliser `PATCH /locale`) |
| Erreurs 4xx : `{message}` | `{code, message}` (+ `errors` pour les formulaires) |
| `country_source` / `language_source` dans les réponses | retirés |
