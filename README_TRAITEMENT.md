# Module de Gestion des Traitements

## Description
Ce module gère les traitements thérapeutiques et leur suivi pour les étudiants dans le cadre du projet UniMind.

## Fonctionnalités

### Gestion des Traitements
- **Création** : Les psychologues peuvent créer des traitements pour les étudiants
- **Consultation** : Tous les acteurs peuvent consulter les traitements selon leurs droits
- **Modification** : Les psychologues peuvent modifier leurs traitements
- **Suppression** : Les psychologues peuvent supprimer leurs traitements

### Gestion des Suivis
- **Création** : Les psychologues et étudiants peuvent créer des suivis
- **Effectuation** : Les étudiants marquent les suivis comme effectués
- **Validation** : Les psychologues valident les suivis effectués par les étudiants
- **Consultation** : Tous les acteurs peuvent consulter les suivis selon leurs droits

## Rôles et Permissions

### Administrateur (ROLE_ADMIN)
- Peut tout voir, modifier et supprimer
- Accès à toutes les fonctionnalités

### Psychologue (ROLE_PSYCHOLOGUE)
- Créer, modifier, supprimer ses traitements
- Créer et modifier les suivis de ses traitements
- Valider les suivis effectués par les étudiants
- Voir ses traitements et suivis associés

### Étudiant (ROLE_ETUDIANT)
- Voir ses traitements
- Créer des suivis pour ses traitements
- Marquer les suivis comme effectués
- Ajouter des observations et évaluations
- Ne peut pas modifier un suivi déjà validé

### Responsable Étudiant (ROLE_RESPONSABLE_ETUDIANT)
- Voir tous les traitements et suivis
- Accès en consultation uniquement

## Fichiers créés

### Controllers
- `src/Controller/TraitementController.php` : Gestion des traitements
- `src/Controller/SuiviTraitementController.php` : Gestion des suivis

### Formulaires
- `src/Form/TraitementType.php` : Formulaire de traitement
- `src/Form/SuiviTraitementType.php` : Formulaire de suivi

### Templates
- `templates/traitement/` : Templates pour les traitements
  - `index.html.twig` : Liste des traitements
  - `new.html.twig` : Création de traitement
  - `show.html.twig` : Détails d'un traitement
  - `edit.html.twig` : Modification de traitement

- `templates/suivi_traitement/` : Templates pour les suivis
  - `new.html.twig` : Création de suivi
  - `show.html.twig` : Détails d'un suivi
  - `mes_suivis.html.twig` : Suivis d'un étudiant
  - `a_valider.html.twig` : Suivis à valider pour les psychologues

### Repository
- `src/Repository/SuiviTraitementRepository.php` : Méthodes de requête personnalisées

### Sécurité
- `src/Security/TraitementVoter.php` : Gestion des permissions

## Routes principales

### Traitements
- `/traitement/` : Liste des traitements
- `/traitement/new` : Créer un traitement
- `/traitement/{id}` : Voir un traitement
- `/traitement/{id}/edit` : Modifier un traitement
- `/traitement/{id}/suivis` : Voir les suivis d'un traitement

### Suivis
- `/suivi-traitement/` : Liste des suivis
- `/suivi-traitement/new/{traitement_id}` : Créer un suivi
- `/suivi-traitement/{id}` : Voir un suivi
- `/suivi-traitement/{id}/edit` : Modifier un suivi
- `/suivi-traitement/{id}/effectuer` : Marquer comme effectué
- `/suivi-traitement/{id}/valider` : Valider un suivi
- `/suivi-traitement/mes-suivis` : Suivis de l'étudiant connecté
- `/suivi-traitement/a-valider` : Suivis à valider pour les psychologues

## Caractéristiques techniques

### Design
- Utilisation du template Argon Dashboard
- Interface responsive et moderne
- Composants Bootstrap 5
- Icônes Font Awesome

### Sécurité
- Contrôle d'accès par Voter
- Validation des formulaires
- Protection CSRF
- Vérification des rôles

### Fonctionnalités avancées
- Statistiques et tableaux de bord
- Système de notification
- Export et impression
- Recherche et filtrage
- Gestion des états (en cours, terminé, suspendu)
- Système d'évaluation par étoiles

## Utilisation

1. **Pour les psychologues** :
   - Accédez à `/traitement/` pour voir vos traitements
   - Cliquez sur "Nouveau Traitement" pour en créer un
   - Utilisez `/suivi-traitement/a-valider` pour valider les suivis

2. **Pour les étudiants** :
   - Accédez à `/traitement/` pour voir vos traitements
   - Utilisez `/suivi-traitement/mes-suivis` pour gérer vos suivis
   - Marquez les suivis comme effectués et ajoutez vos observations

3. **Pour les administrateurs** :
   - Accès complet à toutes les fonctionnalités
   - Gestion de tous les traitements et suivis

## Dépendances

- Symfony 6.x
- Doctrine ORM
- Twig
- Bootstrap 5
- Font Awesome
- Argon Dashboard Template

## Notes importantes

- Le module respecte les principes RBAC (Role-Based Access Control)
- Les formulaires sont adaptés selon le rôle de l'utilisateur
- Les templates utilisent le design Argon Dashboard pour la cohérence
- Le code est commenté et suit les bonnes pratiques Symfony
