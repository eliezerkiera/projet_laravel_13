# Jeu de prompts pour l'agent de codage

## Contenu

| Fichier | Rôle | Dépend de |
|---|---|---|
| `00_contexte_commun.md` | Contexte du projet, faits déjà établis, contrats cibles, règles de travail, format du rapport | — |
| `01_tache_locale_pays.md` | Refonte de la langue et du pays : `GET/PATCH /api/v2/locale`, `Content-Language`, suppression de l'auto-persistance | 00 |
| `02_tache_auth_securite.md` | `expires_in`, `UserResource` unique, codes d'erreur, refresh durci, OTP, anti-énumération, limites de débit, sessions, appareils | 00, **01 terminée** |
| `03_tache_documentation.md` | Réécriture du guide Markdown pour le front/mobile + test de cohérence doc/routes | 00, **01 et 02 terminées** |

## Mode d'emploi

1. Copie ce dossier dans le dépôt, par exemple `docs/agent-prompts/`.
2. Copie aussi l'ancien guide `test_auth_v2_final.md` dans `docs/api/` (la tâche 03 en a besoin).
3. Crée une branche dédiée, puis donne à l'agent, **une tâche à la fois** :

   > Lis `docs/agent-prompts/00_contexte_commun.md` puis exécute `docs/agent-prompts/01_tache_locale_pays.md`. Termine par le rapport demandé.

4. Entre deux tâches, **relis le rapport** et vérifie les décisions listées. Ne lance la suivante que si les tests passent et que le rapport te convient.
5. Si ton outil n'a pas accès aux fichiers du dépôt, colle `00` puis la tâche dans le même message.

## Décisions déjà prises dans les prompts (à valider avant de lancer)

Ces choix vont au-delà de ce que nous avions listé, ou tranchent un point ouvert. Modifie le prompt concerné si l'un d'eux ne te convient pas.

| # | Décision | Où |
|---|---|---|
| 1 | `PATCH /api/v2/auth/locale` **déplacé** vers `PATCH /api/v2/locale` (même chemin que le `GET`) | 01, étape 4 |
| 2 | Un `null` explicite remet **un seul champ** (langue ou pays) en automatique ; `reset_to_auto` remet les deux | 01, étape 4 |
| 3 | Un pays **inactif est refusé** par `PATCH /locale` (plus de `warning`) | 01, étape 4 |
| 4 | `country_id` devient **obligatoire** à `register/complete` s'il y a plus d'un pays actif | 01, étape 6 |
| 5 | Colonnes `country_source` / `language_source` **supprimées** si inutilisées ailleurs | 01, étape 5 |
| 6 | Tout champ `code` d'erreur stable ajouté aux erreurs 4xx (ajout par rapport à la liste initiale) | 02, section M |
| 7 | Les erreurs de refresh **restent en 422** (pas 401) | 02, section M |
| 8 | `register/send-code` pour un email déjà inscrit : réponse `200` identique, **aucun OTP**, un email « compte existant » est envoyé à la place | 02, section G |
| 9 | Access token qui n'expire jamais → il expire (60 min par défaut si rien n'est configuré) | 02, section C |
| 10 | Réutilisation d'un refresh token → **toute la session de l'appareil est révoquée** | 02, section E |
| 11 | Changement de mot de passe → les **autres appareils sont déconnectés** ; suppression de compte → mot de passe exigé | 02, sections I et J |
| 12 | Valeurs par défaut des limites de débit : envoi OTP 1/min et 5/h, login 5/min, vérification 10/min, refresh 60/min, actions sensibles 5/min | 02, section H |

## Ce qui n'a pas pu être vérifié (l'agent le fera)

Je n'ai lu que le middleware de locale, son service et `LocaleController`. Tout le reste de l'authentification (contrôleurs, modèles, migrations, `FormRequest`, `UserResource`, routes, config) est décrit par le **guide**, pas par le code. Les prompts imposent donc un audit avant chaque modification, et demandent à l'agent de suivre la réalité du code quand elle diffère de la description.
