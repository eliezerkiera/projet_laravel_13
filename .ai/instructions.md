## Workflow de modification

### 1. Plan avant modification
Avant toute modification de code (nouveau fichier, édition, migration, refactor),
présente un plan concis avant d'écrire quoi que ce soit :
- Fichiers qui seront créés/modifiés/supprimés
- Approche technique choisie (et alternative écartée si pertinent)
- Impact sur la base de données (migrations, seeders) si applicable
- Risques ou points d'incertitude

Attends ma validation explicite avant d'exécuter le plan.

Exception : corrections triviales à un seul fichier sans impact fonctionnel
(typo, formatage, commentaire) — pas besoin de plan préalable.

### 2. Mise à jour du changelog
Après toute modification validée et terminée, ajoute une entrée en tête de
`.ai/changelog.md` (pas à la fin) avec :
- Date
- Résumé du travail effectué (2-5 lignes, orienté "ce qui a changé et pourquoi")
- Fichiers principaux touchés
- Migrations ajoutées, le cas échéant (nom du fichier de migration)

Format d'entrée :
​```markdown
## [DATE] — {résumé court}
- {détail 1}
- {détail 2}
Fichiers : `app/Models/Invoice.php`, `database/migrations/..._create_invoices_table.php`
​```

Ne jamais réécrire ou supprimer les entrées précédentes — uniquement ajouter.