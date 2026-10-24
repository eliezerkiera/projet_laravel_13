# Contexte

J'ai un fichier `openapi.yaml` a la racine du projet qui décrit mon API Laravel v2 (authentification via Laravel Sanctum, tokens Bearer). Je dois pouvoir **l'importer sans erreur dans Insomnia** (File > Import) et obtenir une collection de requêtes exploitable. Actuellement, l'import échoue ou produit un résultat incomplet.

# Objectif

Analyser le fichier, détecter toutes les erreurs (syntaxe YAML, non-conformité à la spécification OpenAPI, problèmes de compatibilité avec Insomnia), les corriger, puis me prouver que le fichier est valide.

# Étape 0 : Diagnostic obligatoire AVANT toute modification

1. Identifie la version de la spec utilisée (`openapi: 3.0.x`, `3.1.x` ou `swagger: 2.0`) et vérifie qu'elle est cohérente avec la syntaxe réellement employée dans le fichier (par exemple, `nullable` est du 3.0, tandis que `type: [string, "null"]` est du 3.1 ; les deux ne doivent pas être mélangés).
2. Valide le fichier avec un outil, dans cet ordre de préférence, selon ce qui est disponible (sans modifier les dépendances du projet Laravel, utilise `npx` ou une installation temporaire hors projet) :
   - `npx @redocly/cli lint openapi.yaml`
   - `npx @stoplight/spectral-cli lint openapi.yaml`
   - à défaut, un parseur YAML + validation manuelle de la structure
3. Si les outils ne sont pas utilisables (pas de réseau, etc.), dis-le explicitement et fais une analyse manuelle rigoureuse en le signalant dans le rapport.
4. Présente-moi la liste des problèmes trouvés, classés par gravité (bloquant pour l'import / non conforme à la spec / warning de qualité), AVANT de corriger.

# Points de contrôle spécifiques

**Syntaxe YAML**
- Indentation, tabulations, guillemets manquants (valeurs contenant `:`, `#`, `{`, `*`, `@`), clés dupliquées, ancres/alias invalides.
- Valeurs ambiguës mal typées (par exemple `yes`, `no`, `on`, `null`, des numéros de version comme `2.0` qui doivent être des strings).

**Structure OpenAPI**
- Champs racine obligatoires : `openapi`, `info` (avec `title` et `version`), `paths`.
- Chaque opération a un `responses` non vide, avec au moins une réponse et une `description` sur chacune.
- `operationId` unique dans tout le fichier.
- Paramètres de chemin (`{id}`) : déclarés dans `parameters` avec `in: path` et `required: true`, et présents dans l'URL.
- Pas de `requestBody` sur GET/DELETE/HEAD.
- Tous les `$ref` pointent vers des composants existants (`#/components/schemas/...`, etc.), sans références circulaires bloquantes ni références orphelines cassées.
- Schémas valides : types corrects, `items` obligatoire pour `type: array`, `required` ne listant que des propriétés existantes, `enum` cohérent avec le `type`, `example` conforme au schéma.
- Content types corrects (`application/json`, `multipart/form-data`, etc.) et exemples bien formés.

**Compatibilité Insomnia**
- Un bloc `servers` valide avec une URL absolue (par exemple `http://localhost:8000/api/v2` ou une variable d'URL avec valeur par défaut), car Insomnia s'en sert pour construire la base URL de l'environnement.
- `components.securitySchemes` déclare l'authentification Bearer de Sanctum (`type: http`, `scheme: bearer`), et `security` est appliqué globalement ou par opération, avec les routes publiques (login, register, Google auth) surchargées par `security: []`.
- Les `tags` sont déclarés et utilisés de façon cohérente (Insomnia les utilise pour regrouper les requêtes en dossiers).
- Chaque opération a un `summary` (Insomnia l'utilise comme nom de la requête).
- Pas de fonctionnalités non supportées ou mal supportées à l'import : `$ref` vers des fichiers externes ou des URLs distantes (à résoudre en les inlinant ou en les ramenant dans `components`), `callbacks`/`webhooks` complexes (à signaler plutôt qu'à supprimer silencieusement).

# Règles de correction

- **Ne modifie jamais le contrat réel de l'API** : ne change ni les chemins, ni les méthodes HTTP, ni les noms de champs, ni les codes de réponse, sauf si c'est une erreur manifeste de frappe, que tu me signales alors explicitement.
- Si une information manque et que tu ne peux pas la déduire du fichier (par exemple un schéma de réponse absent), ne l'invente pas : ajoute un schéma minimal et générique, et liste-le dans les points à vérifier. Tu peux t'appuyer sur mes routes et contrôleurs Laravel v2 pour confirmer un comportement, en lecture seule.
- Corrections minimales : conserve l'ordre, les commentaires et le style existants autant que possible. Pas de réécriture complète du fichier.
- Avant de modifier, crée une sauvegarde `openapi.yaml.bak`.
- Garde le fichier en une seule pièce (pas de découpage en plusieurs fichiers).

# Vérification finale

1. Relance l'outil de validation et montre-moi que le résultat est sans erreur (les warnings restants doivent être listés et justifiés).
2. Si possible, simule l'import : par exemple convertis le fichier avec un outil comme `openapi-to-postmanv2` (`npx openapi-to-postmanv2 -s openapi.yaml -o /tmp/out.json`) ou un parseur type `swagger-parser` pour confirmer que la spec se résout entièrement (tous les `$ref` inclus).
3. Vérifie qu'aucun `$ref` n'est cassé et qu'aucun `operationId` n'est dupliqué.

# Livrable final

1. Le tableau des problèmes trouvés : localisation (ligne / chemin), gravité, correction appliquée.
2. Le fichier `openapi.yaml` corrigé.
3. Le résultat de la validation avant / après.
4. Les hypothèses faites et les points qui nécessitent ma vérification.
5. Les étapes pour l'importer dans Insomnia et configurer l'environnement (base URL, variable de token Bearer).