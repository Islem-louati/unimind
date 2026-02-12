# 📋 Checklist Routes et Boutons CRUD

## 🔍 Routes principales
- ✅ `/traitement/` → `app_traitement_index`
- ✅ `/suivi-traitement/` → `app_suivi_traitement_index`

## 📊 TRAITEMENTS - Routes CRUD
- ✅ **CREATE** : `/traitement/new` → `app_traitement_new`
- ✅ **READ** : `/traitement/{id}` → `app_traitement_show`
- ✅ **UPDATE** : `/traitement/{id}/edit` → `app_traitement_edit`
- ✅ **DELETE** : `/traitement/{id}` → `app_traitement_delete`
- ✅ **SUIVIS** : `/traitement/{id}/suivis` → `app_traitement_suivis`

## 📋 SUIVI TRAITEMENTS - Routes CRUD
- ✅ **SELECT** : `/suivi-traitement/new` → `app_suivi_traitement_select_traitement`
- ✅ **CREATE** : `/suivi-traitement/new/{traitement_id}` → `app_suivi_traitement_new`
- ✅ **READ** : `/suivi-traitement/{id}` → `app_suivi_traitement_show`
- ✅ **UPDATE** : `/suivi-traitement/{id}/edit` → `app_suivi_traitement_edit`
- ✅ **DELETE** : `/suivi-traitement/{id}` → `app_suivi_traitement_delete`

## 🎯 Boutons dans templates

### 📄 Page Traitement (`/traitement/`)
- ✅ **Navigation** : "SuiviTraitement" → `app_suivi_traitement_index`
- ✅ **CREATE** : "Nouveau Traitement" → `app_traitement_new` (psy uniquement)
- ✅ **READ** : 👁️ → `app_traitement_show`
- ✅ **READ** : 📅 → `app_traitement_suivis`
- ✅ **CREATE** : ➕ → `app_suivi_traitement_new` (psy + étudiant)
- ✅ **UPDATE** : ✏️ → `app_traitement_edit` (psy uniquement)
- ✅ **DELETE** : 🗑️ → `app_traitement_delete` (psy uniquement)

### 📄 Page SuiviTraitement (`/suivi-traitement/`)
- ✅ **Navigation** : "Traitements" → `app_traitement_index`
- ✅ **CREATE** : "Nouveau Suivi" → `app_suivi_traitement_select_traitement` (psy uniquement)
- ✅ **READ** : 👁️ → `app_suivi_traitement_show`
- ✅ **CREATE** : ➕ → `app_suivi_traitement_new` (conditions)
- ✅ **UPDATE** : ✏️ → `app_suivi_traitement_edit` (conditions)
- ✅ **DELETE** : 🗑️ → `app_suivi_traitement_delete` (conditions)

## 🔐 Permissions
- **Psychologue** : CRUD complet sur traitements + suivis de ses traitements
- **Étudiant** : Lecture traitements + CRUD ses suivis (si non validés)
- **Admin** : Lecture seule sur tout

## 🐛 Problèmes identifiés
- ❌ **Route test** : `app_test_suivi_index` → corrigé en `app_suivi_traitement_index`
- ❌ **fullName errors** → corrigés avec vérifications
- ❌ **Relations non chargées** → corrigées avec jointures

## 🧪 Tests à faire
1. **Créer un traitement** (psy)
2. **Créer un suivi** (psy + étudiant)
3. **Modifier/supprimer** (selon permissions)
4. **Navigation** entre pages
