Voici un guide complet en Markdown :

```markdown
# Migration Laravel : Laragon → WSL2

Guide de migration d’un projet Laravel depuis Laragon (Windows) vers WSL2 (Ubuntu), en utilisant Git/GitHub, **PHP 8.4**, **MySQL 8.4**, et des **ports personnalisés**.

---

## Prérequis

- Windows 10/11
- Compte GitHub
- Projet Laravel déjà versionné sur GitHub
- Connaître le nom de ta base de données et tes identifiants Laragon

---

## 1. Installer WSL2 + Ubuntu

Ouvre **PowerShell en administrateur** :

```powershell
wsl --install
```

Redémarre Windows.  
Au premier lancement d’Ubuntu, crée ton utilisateur Linux (nom + mot de passe).

Vérifie que tu es bien en WSL2 :

```powershell
wsl -l -v
```

---

## 2. Installer PHP 8.4 + extensions Laravel

Dans le terminal Ubuntu :

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl unzip git

# Dépôt Ondřej pour PHP 8.4
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# PHP 8.4 + extensions Laravel
sudo apt install -y \
  php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring \
  php8.4-xml php8.4-bcmath php8.4-curl php8.4-zip php8.4-gd \
  php8.4-intl php8.4-sqlite3 php8.4-readline php8.4-opcache
```

Vérification :

```bash
php -v
```

---

## 3. Installer Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## 4. Installer MySQL 8.4 LTS

```bash
# Ajouter le dépôt officiel MySQL
wget https://dev.mysql.com/get/mysql-apt-config_0.8.34-1_all.deb
sudo dpkg -i mysql-apt-config_0.8.34-1_all.deb
```

Dans le menu → choisis **MySQL Server & Cluster** → **mysql-8.4-lts** → OK.

```bash
sudo apt update
sudo apt install -y mysql-server
sudo service mysql start
```

Sécurisation :

```bash
sudo mysql_secure_installation
```

Création de la base et de l’utilisateur :

```bash
sudo mysql
```

```sql
CREATE DATABASE nom_de_ta_base CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laravel'@'localhost' IDENTIFIED BY 'ton_mot_de_passe_fort';
GRANT ALL PRIVILEGES ON nom_de_ta_base.* TO 'laravel'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Installer Node.js (pour Vite)

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install --lts
node -v
npm -v
```

---

## 6. Cloner le projet depuis GitHub

> **Important** : le projet doit être dans le filesystem Linux (`~/`), jamais sur `/mnt/c`.

```bash
mkdir -p ~/projects
cd ~/projects
git clone https://github.com/ton-username/ton-projet.git
cd ton-projet
```

Installation des dépendances :

```bash
composer install
npm install
```

---

## 7. Configurer l’environnement

```bash
cp .env.example .env
php artisan key:generate
```

Édite le fichier `.env` :

```env
APP_URL=http://localhost:8001

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nom_de_ta_base
DB_USERNAME=laravel
DB_PASSWORD=ton_mot_de_passe_fort
```

---

## 8. Migrer la base de données

1. Depuis Laragon → phpMyAdmin → Export → format SQL.
2. Place le fichier `.sql` accessible depuis WSL (ex: `C:\Users\TonUser\Downloads\dump.sql`).
3. Importe :

```bash
mysql -u laravel -p nom_de_ta_base < /mnt/c/Users/TonUser/Downloads/dump.sql
```

Puis :

```bash
php artisan migrate
php artisan storage:link
php artisan config:clear
php artisan cache:clear
```

---

## 9. Ports personnalisés (éviter les ports par défaut)

### Backend Laravel (port 8001)

```bash
php artisan serve --host=0.0.0.0 --port=8001
```

Accessible depuis Windows :  
→ `http://localhost:8001`

### Frontend Vite (port 5174)

Édite `vite.config.js` :

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5174,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

Puis lance :

```bash
npm run dev
```

Accessible depuis Windows :  
→ `http://localhost:5174`

---

## 10. Alias pratiques (recommandé)

Ajoute à la fin de `~/.bashrc` :

```bash
# Services
alias mysql-start="sudo service mysql start"
alias php-start="sudo service php8.4-fpm start"

# Laravel
alias serve="php artisan serve --host=0.0.0.0 --port=8001"
alias vite="npm run dev"
```

Recharge :

```bash
source ~/.bashrc
```

Utilisation :

```bash
serve    # Laravel sur :8001
vite     # Vite sur :5174
```

---

## 11. VS Code / Cursor

1. Installe l’extension **WSL**.
2. Dans Ubuntu :

```bash
cd ~/projects/ton-projet
code .
```

Tout s’ouvre dans le contexte Linux (terminal, IntelliSense, agents IA…).

---

## 12. Codex CLI (optionnel)

Si tu utilises Codex, installe-le **dans WSL** (pas seulement sur Windows) :

```bash
curl -fsSL https://chatgpt.com/codex/install.sh | sh
```

Puis :

```bash
cd ~/projects/ton-projet
codex
```

---

## Récapitulatif des ports utilisés

| Service          | Port  | URL Windows                  |
|------------------|-------|------------------------------|
| Laravel (artisan)| 8001  | http://localhost:8001        |
| Vite             | 5174  | http://localhost:5174        |
| MySQL            | 3306  | (interne à WSL)              |

---

## Commandes utiles au quotidien

```bash
# Démarrer MySQL
sudo service mysql start

# Lancer le backend
serve

# Lancer le frontend
vite

# Vider les caches
php artisan config:clear && php artisan cache:clear && php artisan view:clear
```

---

## Points importants à retenir

- Le projet doit toujours vivre dans `~/projects/...` (filesystem Linux).
- Utilise `--host=0.0.0.0` pour que Windows puisse accéder aux serveurs.
- Ne travaille jamais directement sur `/mnt/c/...` (très lent).
- VS Code / Cursor en mode **Remote-WSL** est fortement recommandé.

---

*Guide généré pour PHP 8.4 + MySQL 8.4 + ports personnalisés.*
```

Tu peux copier-coller ce contenu dans un fichier `MIGRATION-WSL.md` à la racine de ton projet ou dans un dépôt de notes.