# MISSION : AUDIT COMPLET DE CONFORMITÉ ENTRE UNE API REST LARAVEL/SANCTUM ET SA DOCUMENTATION OPENAPI

## 1. CONTEXTE DU PROJET

Le projet contient :

- Un backend API REST développé avec Laravel.
- Un système d'authentification basé sur Laravel Sanctum.
- Un fichier de documentation OpenAPI nommé `openapi.yaml`.
- Des routes, contrôleurs, middlewares, règles de validation, API Resources et autres composants qui déterminent le comportement réel de l'API.
- Un environnement de développement dans lequel je peux tester manuellement les endpoints avec Insomnia.

Je souhaite vérifier que la documentation OpenAPI correspond fidèlement à l'implémentation réelle de mon API.

Je réaliserai personnellement les tests HTTP avec Insomnia. Tu dois analyser le code, préparer les tests et exploiter les résultats que je te communiquerai. Tu ne dois pas exécuter les requêtes HTTP à ma place.

Le chemin exact du fichier OpenAPI doit être déterminé en inspectant le dépôt.

## 2. OBJECTIF PRINCIPAL

Réaliser un audit approfondi de conformité entre :

1. Le code source de l'API Laravel.
2. Le fichier `openapi.yaml`.
3. Les comportements observés lors des tests HTTP que je réaliserai avec Insomnia.

L'objectif est de détecter :

- Les endpoints réels absents de la documentation.
- Les endpoints documentés qui n'existent plus ou qui ne sont pas enregistrés.
- Les méthodes HTTP et chemins incorrects.
- Les paramètres, champs et types incorrects.
- Les différences entre les validations réelles et les schémas documentés.
- Les réponses HTTP et JSON incorrectement documentées.
- Les erreurs ou omissions concernant l'authentification Sanctum.
- Les incohérences entre les schémas OpenAPI et les réponses de l'API.
- Les références OpenAPI invalides et les problèmes de syntaxe YAML.
- Les incohérences qui pourraient gêner la génération de clients API ou l'intégration de frontends tels que React ou Flutter.

La priorité est de garantir une documentation fiable pour les développeurs qui consommeront cette API.

## 3. RÈGLES IMPÉRATIVES

### 3.1. Préserver le projet

Pendant l'audit initial :

- Travailler en lecture seule.
- Ne modifier aucun fichier.
- Ne supprimer, renommer ou déplacer aucun fichier.
- Ne modifier aucune route, aucun contrôleur, aucune validation et aucun comportement du backend.
- Ne modifier ni les migrations ni les données.
- Ne modifier aucun fichier de configuration.
- Ne pas créer de commit.
- Ne pas réinitialiser Git et ne pas annuler de modifications existantes.
- Ne pas installer de dépendance sans mon autorisation.

Examiner l'état Git avant de commencer et préserver toutes les modifications déjà présentes dans le dépôt.

Ne jamais utiliser de commande destructive pour nettoyer l'environnement.

### 3.2. Respecter les faits

- Le code source constitue la principale source de vérité pour l'analyse statique.
- Les résultats des tests constituent la source de vérité pour les comportements effectivement observés dans les scénarios testés.
- Ne pas inventer de routes, de champs, de codes HTTP, de réponses ou de règles métier.
- Ne pas considérer qu'un endpoint fonctionne simplement parce qu'une route est déclarée.
- Ne pas affirmer qu'un test a été effectué si ce n'est pas le cas.
- Distinguer les faits vérifiés, les déductions et les points incertains.
- Ne jamais considérer que la documentation actuelle est correcte sans vérification.

### 3.3. Protéger les données et les secrets

- Ne jamais exposer les clés API, mots de passe, tokens, cookies de session ou autres secrets.
- Ne pas afficher le contenu des fichiers `.env` dans le rapport.
- Ne pas utiliser de données personnelles réelles.
- Ne jamais effectuer de tests destructifs sur une base de données de production.
- Ne pas utiliser de véritables comptes ou tokens de production pour préparer les tests.
- Ne pas lancer de commandes susceptibles de modifier des données sans autorisation.

## 4. PHASE 1 — COMPRENDRE L'ARCHITECTURE DU PROJET

Commencer par examiner la structure du dépôt.

Identifier :

- La version de PHP.
- La version de Laravel.
- La version de Sanctum.
- La version OpenAPI utilisée.
- Le fichier `openapi.yaml` et les éventuels autres fichiers de documentation API.
- Les fichiers de routes réellement utilisés par l'application.
- Les contrôleurs API.
- Les Form Requests.
- Les API Resources et transformations de réponses.
- Les middlewares.
- Les politiques, gates, rôles et permissions éventuels.
- Les mécanismes de gestion des exceptions.
- Les modèles et relations utiles à la compréhension des réponses.
- Les tests existants concernant l'API.
- Les éventuels mécanismes de pagination, filtrage, recherche, tri et versionnement.

Examiner les fichiers de configuration pertinents sans afficher leurs secrets.

Ne pas supposer que l'application utilise la structure Laravel par défaut. Déterminer l'organisation réelle du projet avant de commencer les comparaisons.

## 5. PHASE 2 — ANALYSER LES ROUTES

Utiliser notamment `php artisan route:list`, lorsque l'environnement le permet, ainsi que l'inspection des fichiers de routes.

Vérifier :

- Les méthodes HTTP.
- Les chemins réels.
- Les paramètres de chemin.
- Les préfixes de routes et de versions.
- Les groupes de routes.
- Les middlewares appliqués.
- Les noms de routes, à titre d'information complémentaire.
- Les routes publiques et protégées.
- Les éventuelles routes déclarées dynamiquement.
- Les routes conditionnelles ou dépendantes de l'environnement.
- Les endpoints associés à plusieurs méthodes HTTP.

Comparer les routes réellement enregistrées avec les opérations présentes dans la section `paths` du document OpenAPI.

Pour chaque endpoint :

1. Vérifier si la route est enregistrée.
2. Identifier le contrôleur et la méthode correspondants.
3. Examiner les middlewares et les autorisations.
4. Examiner l'implémentation de la requête et de la réponse.
5. Comparer ces informations avec la documentation.

Tenir compte des paramètres de chemin et des différentes méthodes HTTP afin d'éviter les erreurs de comptage.

Ne pas confondre l'absence d'une route dans un fichier particulier avec son absence effective de l'application.

## 6. PHASE 3 — ANALYSER LES REQUÊTES ET LES VALIDATIONS

Pour chaque endpoint, examiner les données qu'il accepte.

Vérifier :

- Les paramètres de chemin.
- Les paramètres de requête.
- Les en-têtes requis.
- Le type de contenu accepté.
- Les champs du corps de requête.
- Les types de données.
- Les champs obligatoires et facultatifs.
- Les valeurs nulles.
- Les longueurs minimales et maximales.
- Les formats de données.
- Les valeurs autorisées.
- Les règles conditionnelles.
- Les fichiers et téléchargements éventuels.
- Les paramètres de pagination, de recherche, de filtrage et de tri.

Examiner les Form Requests, les appels de validation dans les contrôleurs, les règles métier et les autres mécanismes qui peuvent modifier le comportement réel.

Comparer ces règles avec les schémas OpenAPI correspondants.

Ne pas se limiter à lire les Form Requests : vérifier si les règles sont effectivement appliquées par l'endpoint.

## 7. PHASE 4 — ANALYSER LES RÉPONSES

Pour chaque endpoint, identifier les réponses possibles à partir du code source.

Examiner :

- Les codes HTTP utilisés.
- La structure JSON.
- Les noms des champs.
- Les types des valeurs.
- Les champs facultatifs et nullables.
- Les objets imbriqués.
- Les tableaux et collections.
- Les formats de date et d'heure.
- Les formats de pagination.
- Les messages d'erreur.
- Les erreurs de validation.
- Les erreurs d'authentification.
- Les erreurs d'autorisation.
- Les réponses en cas de ressource inexistante.
- Les réponses sans contenu, lorsqu'elles existent.
- Les exceptions pouvant produire des réponses particulières.

Examiner les contrôleurs, les Resources, les gestionnaires d'exceptions et les tests existants.

Distinguer les réponses garanties par le code de celles qui dépendent d'une configuration, d'un service externe ou d'une condition particulière.

Comparer les résultats avec les sections `responses`, `content`, `schema` et les composants réutilisables du document OpenAPI.

Ne pas supposer que toutes les erreurs utilisent le même format JSON.

## 8. PHASE 5 — ANALYSER L'AUTHENTIFICATION LARAVEL SANCTUM

Déterminer précisément le mécanisme d'authentification utilisé.

Ne pas supposer que Sanctum signifie systématiquement authentification Bearer.

Vérifier si le projet utilise :

- Des tokens personnels Sanctum.
- Une authentification SPA basée sur les cookies et les sessions.
- Une combinaison de ces mécanismes.
- Des middlewares ou mécanismes complémentaires.

Examiner :

- Les endpoints de connexion et de déconnexion.
- Les endpoints de récupération du profil.
- Les routes publiques.
- Les routes protégées.
- Les permissions supplémentaires.
- Les mécanismes de révocation des tokens, s'ils existent.
- Les exigences CSRF applicables.
- Les réponses lorsqu'un utilisateur n'est pas authentifié.
- Les réponses lorsqu'un utilisateur authentifié n'a pas les permissions requises.

Comparer ces informations avec :

- `components.securitySchemes`.
- Les déclarations `security` globales.
- Les déclarations `security` propres à chaque opération.
- Les réponses d'erreur documentées.

Vérifier que la documentation représente fidèlement les différences entre authentification, autorisation et accès public.

## 9. PHASE 6 — VÉRIFIER LA VALIDITÉ TECHNIQUE D'OPENAPI

Examiner le fichier `openapi.yaml` indépendamment du code Laravel.

Vérifier :

- La syntaxe YAML.
- La version OpenAPI déclarée.
- La structure générale du document.
- Les métadonnées et les serveurs.
- Les chemins et les opérations.
- Les paramètres.
- Les corps de requête.
- Les réponses.
- Les schémas.
- Les références `$ref`.
- Les composants réutilisables.
- Les schémas d'authentification.
- Les codes HTTP.
- Les exemples.
- Les types, formats et contraintes.
- Les champs requis, facultatifs et nullables.
- Les déclarations de contenu.
- Les éventuelles incohérences entre schémas réutilisés.

Utiliser un validateur OpenAPI déjà disponible dans l'environnement, si possible.

Ne pas installer de dépendance sans mon autorisation.

Distinguer clairement :

1. Les erreurs de syntaxe YAML.
2. Les erreurs structurelles OpenAPI.
3. Les incohérences entre la documentation et le code.
4. Les incohérences qui ne peuvent être confirmées qu'avec des tests HTTP.

## 10. PHASE 7 — PRÉPARER LE PLAN DE TESTS MANUELS INSOMNIA

Je réaliserai moi-même tous les tests HTTP avec Insomnia.

Tu ne dois pas envoyer les requêtes HTTP à ma place.

À partir de l'analyse statique, préparer un plan de tests détaillé et directement exploitable dans Insomnia.

### 10.1. Organiser les tests

Organiser les tests par endpoint, puis par scénario.

Inclure, lorsque cela est pertinent :

- Un scénario nominal de succès.
- Les données invalides.
- Les champs obligatoires manquants.
- Les valeurs limites.
- Les paramètres incorrects.
- Les ressources inexistantes.
- Les accès sans authentification.
- Les tokens invalides ou expirés, si le mécanisme le permet.
- Les utilisateurs authentifiés sans permissions suffisantes.
- Les opérations de création, de lecture, de modification et de suppression.
- Les cas de pagination, de recherche et de filtrage.
- Les erreurs particulières prévues par l'implémentation.

Ne pas créer artificiellement des scénarios qui ne correspondent pas aux fonctionnalités du projet.

### 10.2. Informations obligatoires pour chaque test

Pour chaque scénario, fournir :

- Un identifiant unique, par exemple `AUTH-001` ou `USER-003`.
- L'endpoint concerné.
- L'objectif du test.
- La méthode HTTP.
- L'URL ou le chemin relatif.
- Les paramètres de chemin et de requête.
- Les en-têtes nécessaires.
- Le type d'authentification.
- Le corps exact de la requête, le cas échéant.
- Le résultat attendu selon le code source.
- Le résultat actuellement déclaré dans OpenAPI.
- Les éléments précis à contrôler dans la réponse.
- Le critère de réussite ou d'échec.
- L'écart potentiel que le test doit confirmer ou infirmer.

Utiliser des variables d'environnement Insomnia, par exemple `base_url`, pour éviter de répéter l'adresse du serveur.

Ne pas inventer la valeur de `base_url`. Proposer une variable à configurer selon mon environnement.

Ne jamais inventer de token d'authentification. Indiquer comment je dois utiliser un token de test obtenu légitimement ou comment réaliser le scénario sans authentification.

Pour les opérations destructives, préciser les précautions et les données de test nécessaires.

### 10.3. Présentation du plan

Fournir un tableau de synthèse comportant au minimum :

- ID du test.
- Endpoint.
- Scénario.
- Statut HTTP attendu.
- Éléments de réponse à vérifier.
- Priorité.
- Résultat Insomnia, initialement « Non exécuté ».
- Conclusion, initialement « En attente ».

Fournir ensuite les détails complets des requêtes.

Ne jamais marquer un test comme réussi ou échoué avant que je communique son résultat.

### 10.4. S'arrêter à cette étape

Après avoir terminé le plan de tests :

- Présenter le rapport d'analyse statique.
- Présenter le plan de tests Insomnia.
- Lister les informations restant à confirmer.
- Attendre mes résultats de tests.

Ne pas passer automatiquement à la correction du fichier.

## 11. PHASE 8 — EXPLOITER MES RÉSULTATS INSOMNIA

Lorsque je communiquerai les résultats des tests, les examiner en les reliant aux endpoints et aux scénarios correspondants.

Je pourrai transmettre :

- Le code HTTP obtenu.
- Les en-têtes pertinents.
- Le corps JSON de la réponse.
- Les messages d'erreur.
- Des captures d'écran.
- Les différences entre le résultat observé et le résultat attendu.

Pour chaque test reçu :

1. Identifier le test concerné.
2. Comparer le résultat observé au comportement attendu selon le code.
3. Comparer le résultat observé à la documentation OpenAPI.
4. Déterminer si la documentation est conforme.
5. Identifier les écarts confirmés.
6. Déterminer si le problème semble provenir de la documentation, du comportement de l'API ou d'une configuration particulière.
7. Indiquer les vérifications supplémentaires éventuellement nécessaires.

Ne pas déduire le comportement de tous les cas possibles à partir d'un seul test.

Ne pas considérer une réponse HTTP comme suffisante pour valider toute la structure JSON : examiner aussi les champs, les types, les valeurs nulles et les objets imbriqués pertinents.

Si les résultats sont incomplets, me demander uniquement les informations nécessaires à la conclusion.

Actualiser le tableau de suivi des tests avec les résultats réellement observés.

## 12. PHASE 9 — RAPPORT FINAL DE CONFORMITÉ

Lorsque l'analyse statique et les tests communiqués sont suffisamment complets, produire un rapport comprenant les sections suivantes.

### A. Résumé exécutif

Indiquer :

- L'état général de la documentation.
- Le nombre d'endpoints et d'opérations identifiés.
- Le nombre d'opérations conformes.
- Le nombre d'opérations présentant des écarts.
- Le nombre d'opérations non vérifiées.
- Les principales erreurs.
- Les risques pour les clients de l'API.

Définir précisément la méthode de comptage. Ne pas mélanger le nombre de chemins, le nombre d'opérations HTTP et le nombre de scénarios de test.

### B. Tableau des écarts

Pour chaque écart, fournir :

- L'identifiant de l'écart.
- La méthode HTTP et le chemin.
- La catégorie.
- La description du problème.
- Le comportement réel déduit du code ou observé.
- Le comportement actuellement documenté.
- La preuve disponible.
- Les fichiers concernés et leurs lignes, si identifiables.
- Le résultat du test Insomnia correspondant, s'il existe.
- La gravité : critique, élevée, moyenne ou faible.
- La correction recommandée.

### C. Inventaire des opérations

Présenter séparément :

1. Les opérations conformes.
2. Les opérations absentes de la documentation.
3. Les opérations documentées mais non retrouvées dans les routes enregistrées.
4. Les opérations présentant des différences de paramètres.
5. Les opérations présentant des différences de réponses.
6. Les problèmes d'authentification ou d'autorisation.
7. Les opérations qui n'ont pas été suffisamment vérifiées.

### D. Limites

Préciser les vérifications qui restent impossibles ou incomplètes et pourquoi.

Ne pas déclarer l'ensemble de l'API conforme si certains endpoints importants n'ont pas été vérifiés.

## 13. PHASE 10 — CORRIGER LA DOCUMENTATION APRÈS MON AUTORISATION

Cette phase ne doit commencer qu'après mon autorisation explicite.

Par défaut, corriger uniquement le fichier `openapi.yaml`.

Les corrections doivent être fondées sur :

- Le code source vérifié.
- Les résultats des tests Insomnia que j'ai communiqués.
- Les validations techniques OpenAPI.

Respecter les règles suivantes :

- Ne pas modifier le backend pour le faire correspondre à la documentation.
- Ne pas inventer de comportement manquant.
- Ne pas supprimer des endpoints sans justification.
- Ne pas changer les règles métier.
- Ne pas modifier les réponses réellement émises par l'API.
- Préserver les commentaires et les conventions utiles du document existant.
- Éviter les changements sans rapport avec les écarts constatés.
- Conserver la compatibilité avec la version OpenAPI utilisée par le projet, sauf décision explicite contraire.

Si un problème nécessite une modification du backend, le signaler séparément et attendre une demande explicite avant d'intervenir.

Après correction :

1. Exécuter le validateur OpenAPI disponible.
2. Vérifier les références `$ref`.
3. Refaire la comparaison statique avec le code.
4. Vérifier que les écarts corrigés sont effectivement résolus.
5. Identifier les tests Insomnia qui doivent être rejoués.
6. Fournir un résumé des modifications.
7. Fournir la liste des points restant à vérifier.

Ne pas affirmer que la documentation est entièrement conforme si les tests nécessaires n'ont pas été réalisés.

## 14. LIVRABLES ATTENDUS

À l'issue de la phase d'audit initiale, je veux obtenir :

1. Un rapport d'analyse statique.
2. Un inventaire des opérations de l'API.
3. Un rapport des écarts potentiels.
4. Un plan de tests manuel Insomnia, avec les requêtes détaillées.
5. Une liste des points à confirmer par des tests HTTP.
6. Une recommandation sur l'ordre d'exécution des tests.

Pendant cette première phase, ces livrables doivent être présentés dans ta réponse ou dans des fichiers de rapport distincts uniquement si cela apporte une réelle valeur. Ne modifie pas la documentation existante.

## 15. ORDRE D'EXÉCUTION

Respecter strictement l'ordre suivant :

**Étape 1 :** analyser le dépôt et son état Git.

**Étape 2 :** analyser les routes, les contrôleurs, les validations, les réponses, Sanctum et le fichier OpenAPI.

**Étape 3 :** effectuer les vérifications statiques et valider techniquement le document lorsque possible.

**Étape 4 :** présenter le rapport d'analyse statique et le plan de tests Insomnia.

**Étape 5 :** attendre que je réalise les tests HTTP et que je communique les résultats.

**Étape 6 :** exploiter les résultats et établir le rapport final de conformité.

**Étape 7 :** attendre mon autorisation explicite avant toute modification du fichier `openapi.yaml`.

**Étape 8 :** après autorisation, corriger la documentation, la valider et présenter les changements.

Commence maintenant par l'étape 1. Effectue uniquement l'analyse initiale et ne modifie aucun fichier.


## 16. GESTION DES LIVRABLES DANS LE DÉPÔT

Les rapports et plans de tests doivent être enregistrés dans des fichiers Markdown afin de pouvoir être consultés dans VS Code, versionnés avec Git et réutilisés avec différents agents de code IA.

### 16.1. Organisation des fichiers

Utiliser le dossier suivant à la racine du projet :

`docs/api-audit/`

Créer les fichiers suivants au fil des phases de travail :

1. `docs/api-audit/01-rapport-analyse.md`
2. `docs/api-audit/02-plan-tests-insomnia.md`
3. `docs/api-audit/03-suivi-tests.md`
4. `docs/api-audit/04-rapport-conformite-final.md`

Avant de créer ces fichiers, vérifier que le dossier et les fichiers n'existent pas déjà.

Ne jamais écraser un document existant sans examiner son contenu. Préserver les informations utiles et les modifications déjà présentes. Si un fichier existe, proposer une mise à jour prudente plutôt que de remplacer son contenu aveuglément.

### 16.2. Première phase : rapport et plan de tests

Après l'analyse statique :

- Enregistrer les conclusions dans `01-rapport-analyse.md`.
- Enregistrer les requêtes détaillées et les scénarios de test dans `02-plan-tests-insomnia.md`.
- Créer `03-suivi-tests.md` avec tous les tests identifiés et leur statut initial « Non exécuté ».
- Présenter un résumé des livrables dans ta réponse.

Le rapport doit clairement distinguer les écarts certains des écarts potentiels et des points qui nécessitent des tests HTTP.

Le plan de tests doit être directement exploitable dans Insomnia.

### 16.3. Pendant les tests Insomnia

Lorsque je communique les résultats des tests :

- Mettre à jour `03-suivi-tests.md`.
- Associer chaque résultat à son identifiant de test.
- Conserver les résultats observés et les écarts confirmés.
- Ne jamais inventer un résultat manquant.
- Mettre à jour les rapports si de nouvelles conclusions sont établies.

Ne pas stocker de mots de passe, de tokens réels, de cookies de session ou de données personnelles sensibles dans ces fichiers.

### 16.4. Rapport final

Après l'analyse des résultats des tests, produire ou mettre à jour `04-rapport-conformite-final.md`.

Ce rapport doit présenter les conclusions finales, les écarts confirmés, les corrections recommandées, les tests non exécutés et les limites de l'audit.

Ne pas déclarer la conformité totale si des vérifications importantes restent en attente.

### 16.5. Autorisation de modification de la documentation OpenAPI

La création des fichiers de rapport et du plan de tests est autorisée dans le cadre de cette mission.

En revanche, `openapi.yaml` et les fichiers du backend restent en lecture seule jusqu'à mon autorisation explicite de correction.

Ne pas modifier le code de l'application, les routes, les tests existants ou les configurations.

Ne créer aucun commit Git.

À la fin de chaque phase, indiquer les fichiers créés ou mis à jour et résumer leur contenu.