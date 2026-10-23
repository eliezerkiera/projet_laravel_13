# Contexte

J'ai une API Laravel (uniquement API, pas de vues Blade) avec un système d'authentification complet déjà en place, basé sur **Laravel Sanctum en mode tokens personnels** (`personal_access_tokens`). L'API est **versionnée : je travaille sur la v2**. Je veux ajouter la connexion / inscription via Google avec Laravel Socialite, sans casser l'existant.

# Étape 0 : Analyse obligatoire AVANT d'écrire du code

Explore le projet et résume-moi en quelques lignes ce que tu trouves :
1. La version de Laravel et de PHP (composer.json), et la version de Sanctum.
2. Comment la v2 est organisée : fichier de routes (`routes/api.php` avec groupe `prefix('v2')` ou fichier dédié), namespace des contrôleurs (ex. `App\Http\Controllers\Api\V2`), FormRequests, Resources, services, middlewares et tests de la v2.
3. Comment le login v2 actuel génère le token Sanctum : nom du token, abilities, expiration (`config/sanctum.php`), révocation des anciens tokens, `device_name` éventuel, et le format exact de la réponse JSON (clés, structure, code HTTP).
4. Le modèle User (fillable, hidden, casts, trait `HasApiTokens`) et les migrations de la table users (le champ password est-il nullable ? email_verified_at ?).
5. Les conventions du projet (structure des dossiers, nommage, services/actions, Pest ou PHPUnit).

Réutilise ces conventions. Ne réinvente rien : le token renvoyé après connexion Google doit être créé de la même façon que celui du login v2 (même méthode `createToken`, mêmes abilities, même expiration, même format de réponse JSON).

# Périmètre de version

- Tout le code nouveau vit dans la **v2** uniquement : routes, contrôleurs, FormRequests, Resources, tests.
- Ne modifie rien dans la v1.
- Si la logique métier (service) peut être partagée entre versions, place-la dans un emplacement neutre (ex. `App\Services\Auth`) plutôt que dans un namespace V2.

# Objectif fonctionnel

Implémenter l'authentification Google en mode **stateless** (aucune session, aucun cookie, pas de mode SPA cookie Sanctum) avec deux flux :

**Flux A : Redirection (web)**
- `GET /api/v2/auth/google/redirect` : renvoie en JSON l'URL de redirection Google (`Socialite::driver('google')->stateless()->redirect()->getTargetUrl()`).
- `GET /api/v2/auth/google/callback` : reçoit le `code` de Google, récupère l'utilisateur via `->stateless()->user()`, puis connecte ou crée le compte et renvoie le token Sanctum en JSON.

**Flux B : Token (SPA / mobile)**
- `POST /api/v2/auth/google/token` : reçoit `{ "token": "<access_token Google>" }` (et `device_name` si mon login v2 l'utilise), valide-le côté serveur avec `Socialite::driver('google')->stateless()->userFromToken($token)`, puis connecte ou crée le compte et renvoie le token Sanctum.

Adapte les chemins exacts au préfixe et au nommage réellement utilisés par ma v2 (identifie-les à l'étape 0).

# Règles métier

- Crée une table `social_accounts` (migration) : `id`, `user_id` (FK, cascade on delete), `provider`, `provider_id`, `provider_email` nullable, timestamps, avec un index unique sur (`provider`, `provider_id`). Ajoute le modèle `SocialAccount` et la relation `socialAccounts()` sur User.
- Logique de résolution de l'utilisateur, dans cet ordre :
  1. Si un `social_account` existe pour (google, provider_id) : connecter l'utilisateur lié.
  2. Sinon, si un User existe avec le même email : lier le compte Google à ce User (uniquement si l'email Google est vérifié, sinon refuser avec une erreur claire).
  3. Sinon, créer un nouveau User (nom et email depuis Google, `email_verified_at` = now(), mot de passe nullable ou aléatoire selon ce qui est le plus cohérent avec mon schéma actuel) puis le lier.
- Rends `password` nullable via une migration seulement si nécessaire, sans casser les données existantes.
- Toute la logique métier va dans un service ou une action dédiée (ex. `GoogleAuthService`), pas dans le contrôleur. Le contrôleur reste fin.
- Mets les opérations de création/liaison dans une transaction DB.
- Le token Sanctum est créé après la transaction, avec la même logique que le login v2.

# Configuration

- Installe `laravel/socialite` via composer s'il n'est pas déjà présent.
- Ajoute la config `google` dans `config/services.php` (client_id, client_secret, redirect) lue depuis `.env`.
- Ajoute dans `.env.example` : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` (avec en exemple une URI cohérente avec la route v2 du callback). Ne mets jamais de vraies valeurs.
- Utilise toujours `->stateless()`.

# Sécurité et gestion d'erreurs

- Valide l'entrée du flux B avec une FormRequest v2.
- Gère les exceptions Socialite (token invalide, code expiré, `InvalidStateException`, erreurs Guzzle) et renvoie des réponses JSON propres (401 ou 422) au même format d'erreur que le reste de ma v2, sans fuite d'information interne.
- Applique le même rate limiting (throttle) que mes routes d'auth v2 existantes.
- Ne logge jamais de tokens ni de secrets.
- Respecte les règles existantes au moment de la connexion (compte désactivé, vérification d'email, rôles, etc.).

# Tests

Écris des tests dans le dossier et le framework de tests de la v2, en mockant Socialite, sans jamais appeler Google pour de vrai :
- Nouvel utilisateur : compte créé + social_account créé + token Sanctum renvoyé (vérifie la présence dans `personal_access_tokens` et que le token permet d'appeler une route protégée v2).
- Utilisateur existant avec le même email : compte lié, pas de doublon.
- Retour d'un utilisateur déjà lié : connexion directe.
- Token Google invalide : réponse d'erreur 401/422.
- Champ `token` manquant : erreur de validation.
- Email Google non vérifié avec email déjà existant : refus.
Lance toute la suite de tests et assure-toi que les tests existants (v1 et v2) passent toujours.

# Contraintes

- Ne modifie pas le comportement du login/register actuel, ni de la v1.
- Fais des changements minimaux et cohérents avec le code existant, sans refactor non demandé.
- N'ajoute aucune dépendance autre que `laravel/socialite`.
- Respecte le style de code du projet (lance Pint / le linter s'il est configuré).

# Livrable final

À la fin, donne-moi :
1. La liste des fichiers créés et modifiés.
2. Les commandes à exécuter (migrate, etc.).
3. Les étapes de configuration côté Google Cloud Console (URI de redirection autorisée correspondant à la route v2, écran de consentement OAuth, type d'identifiants).
4. Des exemples `curl` pour tester chaque endpoint v2, y compris un appel à une route protégée avec le token Sanctum obtenu.
5. Les hypothèses que tu as dû faire et les points qui méritent ma vérification.
6. Cree un fichier markdownd dans lequelle tu expliquera comment tester avec le logiciel Insomnia
7. Mets a jours le fichier `openapi.yaml` pour integrer les nouvelles routes