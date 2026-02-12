# Configuration GitHub pour le projet PIDEV

## Initialisation du dépôt Git

```bash
# Initialiser le dépôt si ce n'est pas déjà fait
git init

# Ajouter tous les fichiers
git add .

# Premier commit
git commit -m "Initial commit - Module gestion des traitements"
```

## Configuration du dépôt distant

```bash
# Ajouter le dépôt distant (remplacer avec votre URL)
git remote add origin https://github.com/votre-username/unimind-vghof.git

# Pousser vers GitHub
git push -u origin main
```

## Structure des branches recommandée

```
main                 # Branche principale de production
develop              # Branche de développement
feature/traitement   # Branche pour le module traitement
feature/api          # Branche pour les fonctionnalités API
hotfix/corrections   # Branches pour les corrections urgentes
```

## Workflow de collaboration

### 1. Pour chaque nouvelle fonctionnalité
```bash
# Créer une nouvelle branche depuis develop
git checkout develop
git pull origin develop
git checkout -b feature/nouvelle-fonctionnalite

# Travailler sur la fonctionnalité
# ... faire les modifications ...

# Commiter les changements
git add .
git commit -m "feat: ajouter nouvelle fonctionnalité"

# Pousser la branche
git push origin feature/nouvelle-fonctionnalite
```

### 2. Pour les corrections
```bash
# Créer une branche de hotfix depuis main
git checkout main
git pull origin main
git checkout -b hotfix/correction-urgente

# Faire la correction
# ... modifier le code ...

# Commiter et pousser
git add .
git commit -m "fix: correction urgente du bug X"
git push origin hotfix/correction-urgente
```

## Configuration des fichiers .gitignore

```gitignore
# Variables d'environnement
.env
.env.local
.env.*.local

# Dossiers de cache
/var/cache/*
/var/log/*
/vendor/

# Fichiers de configuration IDE
.idea/
.vscode/
*.swp
*.swo

# Fichiers temporaires
*.tmp
*.temp

# Node modules (si applicable)
node_modules/

# Build files
/public/build/
/public/bundles/
```

## Script de déploiement automatique

Créer un fichier `deploy.sh` à la racine :

```bash
#!/bin/bash

# Script de déploiement pour le projet UniMind

echo "Début du déploiement..."

# Mettre à jour le code
git pull origin main

# Installer les dépendances
composer install --no-dev --optimize-autoloader

# Vider le cache
php bin/console cache:clear --env=prod

# Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Donner les permissions
chmod -R 755 var/
chmod -R 755 public/

echo "Déploiement terminé avec succès !"
```

## Configuration de la CI/CD avec GitHub Actions

Créer `.github/workflows/deploy.yml` :

```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, ctype, iconv, intl, pdo, pdo_mysql, dom, filter, gd, iconv, json, mbstring, pdo
        
    - name: Copy environment file
      run: cp .env.prod .env.local
      
    - name: Install dependencies
      run: composer install --no-progress --no-suggest --prefer-dist --optimize-autoloader
      
    - name: Clear cache
      run: php bin/console cache:clear
      
    - name: Run migrations
      run: php bin/console doctrine:migrations:migrate --no-interaction
      
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.SSH_KEY }}
        script: |
          cd /var/www/unimind-vghof
          git pull origin main
          composer install --no-dev --optimize-autoloader
          php bin/console cache:clear --env=prod
          php bin/console doctrine:migrations:migrate --no-interaction
```

## Instructions pour l'équipe

### Pour commencer à travailler sur le projet

1. **Cloner le dépôt**
   ```bash
   git clone https://github.com/votre-username/unimind-vghof.git
   cd unimind-vghof
   ```

2. **Installer les dépendances**
   ```bash
   composer install
   ```

3. **Configurer l'environnement**
   ```bash
   cp .env.example .env
   # Modifier .env avec vos configurations
   ```

4. **Créer la base de données**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:schema:create
   ```

### Règles de commit

Utiliser des messages de commit clairs et standardisés :

```
feat: nouvelle fonctionnalité
fix: correction de bug
docs: mise à jour de la documentation
style: modifications de style (formatage, etc.)
refactor: refactoring du code
test: ajout ou modification de tests
chore: tâche de maintenance
```

### Processus de Pull Request

1. Créer une branche pour chaque fonctionnalité
2. Faire les commits avec des messages clairs
3. Pousser la branche
4. Créer une Pull Request vers `develop`
5. Faire réviser le code par au moins un autre membre
6. Merger la Pull Request après validation

### Gestion des conflits

En cas de conflit lors du `git pull` :

```bash
# Mettre à jour la branche principale
git checkout develop
git pull origin develop

# Rebasculer votre branche
git checkout feature/votre-branche
git rebase develop

# Résoudre les conflits manuellement
# Continuer le rebase
git add .
git rebase --continue

# Pousser la branche corrigée
git push origin feature/votre-branche --force-with-lease
```

## Checklist avant chaque commit

- [ ] Le code passe les tests unitaires
- [ ] Le code respecte les standards de codage PSR
- [ ] Les commentaires sont clairs et pertinents
- [ ] La documentation est à jour
- [ ] Les messages de commit sont clairs
- [ ] Aucune information sensible n'est commitée

## Outils recommandés

- **GitKraken** ou **SourceTree** : Interface graphique pour Git
- **PHPStorm** : IDE intégré avec Git
- **GitHub Desktop** : Client GitHub officiel
- **Pre-commit hooks** : Validation automatique avant commit
