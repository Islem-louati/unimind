# 📋 Checklist Test Complet - Module Traitement/Suivi

## 🔐 Test d'authentification
- [ ] Login psychologue : `psycho@test.com` / `test123`
- [ ] Login étudiant : `etudiant@test.com` / `test123`
- [ ] Login admin : `admin@test.com` / `test123`
- [ ] Logout fonctionne

## 📊 TRAITEMENTS

### Page liste (`/traitement/`)
- [ ] Affiche les traitements du psychologue connecté
- [ ] Bouton "Nouveau Traitement" visible (psy uniquement)
- [ ] Cartes statistiques fonctionnelles
- [ ] Filtres et recherche fonctionnent
- [ ] Tri par colonnes fonctionne

### Actions sur chaque traitement
- [ ] 👁️ **Voir** : Lien vers détail fonctionne
- [ ] 📅 **Voir suivis** : Lien vers liste des suivis fonctionne
- [ ] ➕ **Ajouter suivi** : Visible pour étudiants, fonctionne
- [ ] ✏️ **Modifier** : Visible pour psychologue, fonctionne
- [ ] 🗑️ **Supprimer** : Visible pour psychologue, **à tester**

### Création (`/traitement/new`)
- [ ] Formulaire s'affiche (psy uniquement)
- [ ] Champ étudiant présent et fonctionnel
- [ ] Validation fonctionne
- [ ] Redirection vers détail après création

### Modification (`/traitement/{id}/edit`)
- [ ] Formulaire pré-rempli
- [ ] Modifications sauvegardées
- [ ] Redirection vers détail

### Détail (`/traitement/{id}`)
- [ ] Affiche toutes les infos
- [ ] Boutons d'actions cohérents
- [ ] Navigation retour fonctionne

## 📋 SUIVI TRAITEMENTS

### Page liste (`/suivi-traitement/`)
- [ ] Affiche les suivis selon le rôle
- [ ] Bouton "Nouveau Suivi" (psy uniquement)
- [ ] Lien "Traitements" fonctionne
- [ ] Statistiques correctes

### Actions sur chaque suivi
- [ ] 👁️ **Voir** : Fonctionne
- [ ] ➕ **Ajouter** : Visible pour psy/étudiant concerné
- [ ] ✏️ **Modifier** : Visible selon règles, fonctionne
- [ ] 🗑️ **Supprimer** : Visible selon règles, **à tester**

### Création (`/suivi-traitement/new/{traitement_id}`)
- [ ] Formulaire s'affiche
- [ ] Champs selon rôle (psy/étudiant)
- [ ] Création fonctionne

### Navigation
- [ ] Sidebar : Traitements ↔ SuiviTraitement
- [ ] Breadcrumbs cohérents
- [ ] Liens entre pages fonctionnels

## 🎯 Règles métier à vérifier

### Psychologue
- [ ] Voit uniquement ses traitements
- [ ] Peut CRUD ses traitements
- [ ] Peut CRUD tous les suivis de ses traitements

### Étudiant
- [ ] Voit uniquement ses traitements
- [ ] Ne peut PAS créer/modifier/supprimer traitements
- [ ] Peut CRUD ses suivis (si non validés)

### Admin
- [ ] Voit tous les traitements/suivis
- [ ] Ne peut PAS CRUD (lecture seule)

## 🐛 Problèmes identifiés
- [ ] Suppression traitement : **À TESTER**
- [ ] Suppression suivi : **À TESTER**
- [ ] Messages flash s'affichent correctement

## 📝 Notes de test
- Test effectué avec : _________
- Navigateur : _________
- Résultats : _________
