# OwlSettingsBundle

[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF.svg)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.x-000000.svg)](https://symfony.com/)

**[FR]** Bundle Symfony pour le paramétrage global de l'application et les préférences utilisateur (thème, langue, etc.) avec interface auto-générée.
**[EN]** Symfony bundle for global application settings and per-user preferences (theme, language, etc.) with auto-generated UI.

---

## Sommaire / Table of Contents

- [Français](#-français)
  - [Fonctionnalités](#fonctionnalités)
  - [Installation](#installation)
  - [Configuration](#configuration)
  - [Utilisation](#utilisation)
  - [Types de champs](#types-de-champs)
  - [Héritage des préférences](#héritage-des-préférences)
  - [Fonctions Twig](#fonctions-twig)
- [English](#-english)
  - [Features](#features)
  - [Installation](#installation-1)
  - [Configuration](#configuration-1)
  - [Usage](#usage)
  - [Field types](#field-types)
  - [Preference inheritance](#preference-inheritance)
  - [Twig functions](#twig-functions)

---

## 🇫🇷 Français

### Fonctionnalités

- **Deux niveaux de paramétrage** — paramètres globaux (admin) + préférences utilisateur (profil)
- **Builder pattern fluide** — API chainable inspirée du FormBuilder de Symfony
- **Groupes de paramètres** — organisation en onglets dans l'interface admin
- **Héritage** — une préférence utilisateur peut hériter d'un paramètre global si non définie
- **9 types de champs** — text, email, number, boolean (toggle switch), select, textarea, file, color, password
- **Stockage DBAL** — pas besoin de Doctrine ORM, fonctionne avec DBAL seul
- **Cache Symfony** — les paramètres sont cachés, invalidés automatiquement à la modification
- **Interface auto-générée** — templates Twig prêts à inclure pour l'admin et le profil utilisateur
- **Controller AJAX inclus** — routes de sauvegarde prêtes à l'emploi, zéro config
- **JavaScript vanilla** — onglets, soumission AJAX, preview thème/fichier, auto-save
- **CSS par défaut** — nommage BEM, toggle switch, responsive mobile
- **Fonctions Twig** — `owl_setting()` et `owl_user_pref()` pour lire les valeurs partout

### Installation

Ajoutez le repository et le package dans votre `composer.json` :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Jaecko/owl-settings-bundle"
        }
    ],
    "require": {
        "owl-concept/settings-bundle": "dev-main"
    }
}
```

Puis lancez :

```bash
composer update
```

Enregistrez le bundle dans `config/bundles.php` :

```php
return [
    // ...
    OwlConcept\SettingsBundle\OwlSettingsBundle::class => ['all' => true],
];
```

Importez les routes du bundle dans `config/routes/owl_settings.yaml` :

```yaml
owl_settings:
    resource: '@OwlSettingsBundle/Resources/config/routes.yaml'
```

Installez les assets (CSS & JS) :

```bash
php bin/console assets:install
```

Créez les tables en base de données :

```bash
# Prévisualiser le SQL
php bin/console owl:settings:install

# Exécuter
php bin/console owl:settings:install --force
```

Incluez le CSS et le JS dans votre template de base :

```twig
<link rel="stylesheet" href="{{ asset('bundles/owlsettings/css/owl-settings.css') }}">
<script src="{{ asset('bundles/owlsettings/js/owl-settings.js') }}" defer></script>
```

### Configuration

Configuration optionnelle dans `config/packages/owl_settings.yaml` :

```yaml
owl_settings:
    css_class_prefix: owl-settings       # Préfixe CSS (défaut: owl-settings)
    cache_pool: cache.app                # Pool de cache Symfony (défaut: cache.app)
    cache_ttl: 3600                      # Durée du cache en secondes (défaut: 3600)
    settings_table: owl_settings         # Nom de la table des paramètres (défaut: owl_settings)
    preferences_table: owl_user_preferences  # Nom de la table des préférences (défaut: owl_user_preferences)
    user_identifier_method: getUserIdentifier  # Méthode sur l'entité User (défaut: getUserIdentifier)
```

### Utilisation

#### 1. Définir les paramètres globaux

Créez un service pour déclarer vos paramètres. Appelez-le au boot de l'application (par exemple via un EventSubscriber sur `kernel.request` ou dans un CompilerPass) :

```php
use OwlConcept\SettingsBundle\Builder\SettingsBuilder;

class SettingsConfigurator
{
    public function __construct(private SettingsBuilder $builder) {}

    public function configure(): void
    {
        $this->builder->createGroup('general', 'Paramètres généraux')
            ->setDescription('Configuration principale de l\'application')
            ->setIcon('⚙️')
            ->add('app_name', 'Nom de l\'application', 'text', 'Mon CRM')
            ->add('default_language', 'Langue par défaut', 'select', 'fr', [
                'options' => ['fr' => 'Français', 'en' => 'English', 'es' => 'Español']
            ])
            ->add('items_per_page', 'Éléments par page', 'number', 25, [
                'min' => 5, 'max' => 100
            ])
            ->add('maintenance_mode', 'Mode maintenance', 'boolean', false, [
                'help' => 'Active la page de maintenance pour les visiteurs'
            ])
            ->build();

        $this->builder->createGroup('email', 'Configuration email')
            ->setIcon('📧')
            ->setRole('ROLE_ADMIN')
            ->add('from_name', 'Nom d\'expéditeur', 'text', 'Mon CRM')
            ->add('from_email', 'Email d\'expéditeur', 'email', 'noreply@moncrm.com')
            ->add('signature', 'Signature', 'textarea', '')
            ->build();

        $this->builder->createGroup('apparence', 'Apparence')
            ->setIcon('🎨')
            ->add('primary_color', 'Couleur principale', 'color', '#0d6efd')
            ->add('logo', 'Logo', 'file', null, ['accept' => 'image/*'])
            ->build();
    }
}
```

#### 2. Définir les préférences utilisateur

```php
$this->builder->createUserPreferences()
    ->add('theme', 'Thème', 'select', 'light', [
        'options' => ['light' => 'Clair', 'dark' => 'Sombre', 'auto' => 'Automatique (système)']
    ])
    ->add('language', 'Langue', 'select', null, [
        'options' => ['fr' => 'Français', 'en' => 'English'],
        'inherit_from' => 'general.default_language',
    ])
    ->add('items_per_page', 'Éléments par page', 'number', null, [
        'min' => 5, 'max' => 100,
        'inherit_from' => 'general.items_per_page',
    ])
    ->add('notifications_email', 'Notifications par email', 'boolean', true)
    ->add('sidebar_collapsed', 'Barre latérale réduite', 'boolean', false)
    ->build();
```

> Quand `inherit_from` est défini et que l'utilisateur n'a pas choisi de valeur, la préférence hérite automatiquement du paramètre global correspondant.

#### 3. Afficher les pages

**Page admin — Paramètres globaux :**

```twig
{% extends 'admin/base.html.twig' %}

{% block body %}
    <h1>Paramètres</h1>
    {% include '@OwlSettings/admin.html.twig' %}
{% endblock %}
```

**Page profil — Préférences utilisateur :**

```twig
{% extends 'base.html.twig' %}

{% block body %}
    <h1>Mes préférences</h1>
    {% include '@OwlSettings/user_preferences.html.twig' %}
{% endblock %}
```

> Les formulaires sont auto-générés avec les bons types de champs, les valeurs actuelles pré-remplies, et la sauvegarde se fait en AJAX sans rechargement de page.

#### 4. Lire les valeurs dans le code

```php
use OwlConcept\SettingsBundle\Service\SettingsService;

class MyService
{
    public function __construct(private SettingsService $settings) {}

    public function doSomething(): void
    {
        // Paramètre global
        $appName = $this->settings->get('general.app_name');         // 'Mon CRM'
        $perPage = $this->settings->get('general.items_per_page');   // 25 (int)
        $maintenance = $this->settings->get('general.maintenance_mode'); // false (bool)

        // Préférence utilisateur (utilisateur connecté)
        $theme = $this->settings->getUserPreference('theme');        // 'dark'
        $lang = $this->settings->getUserPreference('language');      // 'fr' (hérité du global si non défini)

        // Préférence d'un utilisateur spécifique
        $theme = $this->settings->getUserPreference('theme', $otherUser);
    }
}
```

#### 5. Lire les valeurs en Twig

```twig
{# Paramètre global #}
<title>{{ owl_setting('general.app_name') }}</title>

{# Préférence utilisateur #}
<body class="theme-{{ owl_user_pref('theme') }}">

{# Conditionnel #}
{% if owl_setting('general.maintenance_mode') %}
    <div class="alert">Site en maintenance</div>
{% endif %}
```

#### 6. Modifier les valeurs dans le code

```php
// Modifier un paramètre global
$this->settings->set('general.app_name', 'Nouveau nom');

// Modifier une préférence utilisateur
$this->settings->setUserPreference('theme', 'dark');

// Réinitialiser une préférence (retour à l'héritage/défaut)
$this->settings->resetUserPreference('language');
```

#### Options du GroupBuilder

```php
$this->builder->createGroup('security', 'Sécurité')
    ->setIcon('🔒')                              // Icône dans l'onglet
    ->setRole('ROLE_SUPER_ADMIN')                 // Restreindre l'accès
    ->setDescription('Paramètres de sécurité')    // Description sous le titre
    ->add(...)
    ->build();
```

### Types de champs

| Type | Rendu HTML | Options disponibles |
|------|-----------|---------------------|
| `text` | `<input type="text">` | `placeholder`, `maxlength`, `required` |
| `email` | `<input type="email">` | `placeholder`, `maxlength`, `required` |
| `number` | `<input type="number">` | `min`, `max`, `step` |
| `boolean` | Toggle switch (checkbox) | — |
| `select` | `<select>` | `options` : `['value' => 'Label', ...]` |
| `textarea` | `<textarea>` | `rows` (défaut: 4) |
| `file` | `<input type="file">` avec preview image | `accept` : `'image/*'` |
| `color` | `<input type="color">` | — |
| `password` | `<input type="password">` | `placeholder`, `maxlength` |

### Héritage des préférences

Le système d'héritage fonctionne en 3 niveaux :

```
1. Valeur définie par l'utilisateur    → priorité maximale
2. Paramètre global (inherit_from)     → si l'utilisateur n'a rien défini
3. Valeur par défaut (defaultValue)    → dernier recours
```

Exemple concret :

```php
// Paramètre global
->add('items_per_page', '...', 'number', 25)    // défaut: 25

// Préférence utilisateur
->add('items_per_page', '...', 'number', null, [
    'inherit_from' => 'general.items_per_page',  // hérite du global
])
```

| Cas | Valeur retournée par `owl_user_pref('items_per_page')` |
|-----|--------------------------------------------------------|
| L'utilisateur a choisi 50 | `50` |
| L'utilisateur n'a rien choisi, admin a mis 30 en global | `30` (hérité) |
| L'utilisateur n'a rien choisi, global non modifié | `25` (défaut) |

### Fonctions Twig

| Fonction | Description | Exemple |
|----------|-------------|---------|
| `owl_setting(key)` | Lit un paramètre global | `{{ owl_setting('general.app_name') }}` |
| `owl_user_pref(key)` | Lit une préférence utilisateur | `{{ owl_user_pref('theme') }}` |
| `owl_settings_groups()` | Liste des groupes (pour templates custom) | `{% for g in owl_settings_groups() %}` |
| `owl_settings_all()` | Map de tous les paramètres globaux | `{% set all = owl_settings_all() %}` |
| `owl_user_pref_definitions()` | Définitions des préférences | `{% for d in owl_user_pref_definitions() %}` |
| `owl_user_pref_all()` | Map de toutes les préférences user | `{% set prefs = owl_user_pref_all() %}` |

### Schéma de base de données

Le bundle crée 2 tables via la commande `owl:settings:install` :

**`owl_settings`** — Paramètres globaux

| Colonne | Type | Description |
|---------|------|-------------|
| `setting_key` | VARCHAR(255) PK | Clé qualifiée (ex: `general.app_name`) |
| `setting_value` | TEXT | Valeur sérialisée |
| `setting_group` | VARCHAR(100) | Groupe (ex: `general`) |
| `setting_type` | VARCHAR(50) | Type de champ |
| `created_at` | DATETIME | Date de création |
| `updated_at` | DATETIME | Dernière modification |

**`owl_user_preferences`** — Préférences utilisateur

| Colonne | Type | Description |
|---------|------|-------------|
| `user_id` | VARCHAR(255) PK | Identifiant utilisateur |
| `pref_key` | VARCHAR(255) PK | Clé de préférence |
| `pref_value` | TEXT | Valeur sérialisée |
| `created_at` | DATETIME | Date de création |
| `updated_at` | DATETIME | Dernière modification |

---

## 🇬🇧 English

### Features

- **Two-level settings** — global application settings (admin) + per-user preferences (profile)
- **Fluent builder pattern** — chainable API inspired by Symfony's FormBuilder
- **Setting groups** — organized as tabs in the admin interface
- **Inheritance** — a user preference can inherit from a global setting if not defined
- **9 field types** — text, email, number, boolean (toggle switch), select, textarea, file, color, password
- **DBAL storage** — no Doctrine ORM needed, works with DBAL alone
- **Symfony Cache** — settings are cached, automatically invalidated on change
- **Auto-generated UI** — ready-to-include Twig templates for admin and user profile pages
- **Built-in AJAX controller** — save routes included, zero config needed
- **Vanilla JavaScript** — tabs, AJAX submission, theme/file preview, auto-save
- **Default CSS** — BEM naming, toggle switch, responsive mobile
- **Twig functions** — `owl_setting()` and `owl_user_pref()` to read values anywhere

### Installation

Add the repository and package to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Jaecko/owl-settings-bundle"
        }
    ],
    "require": {
        "owl-concept/settings-bundle": "dev-main"
    }
}
```

Then run:

```bash
composer update
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    OwlConcept\SettingsBundle\OwlSettingsBundle::class => ['all' => true],
];
```

Import the bundle routes in `config/routes/owl_settings.yaml`:

```yaml
owl_settings:
    resource: '@OwlSettingsBundle/Resources/config/routes.yaml'
```

Install the assets (CSS & JS):

```bash
php bin/console assets:install
```

Create the database tables:

```bash
# Preview the SQL
php bin/console owl:settings:install

# Execute
php bin/console owl:settings:install --force
```

Include the CSS and JS in your base template:

```twig
<link rel="stylesheet" href="{{ asset('bundles/owlsettings/css/owl-settings.css') }}">
<script src="{{ asset('bundles/owlsettings/js/owl-settings.js') }}" defer></script>
```

### Configuration

Optional configuration in `config/packages/owl_settings.yaml`:

```yaml
owl_settings:
    css_class_prefix: owl-settings       # CSS prefix (default: owl-settings)
    cache_pool: cache.app                # Symfony cache pool (default: cache.app)
    cache_ttl: 3600                      # Cache duration in seconds (default: 3600)
    settings_table: owl_settings         # Settings table name (default: owl_settings)
    preferences_table: owl_user_preferences  # Preferences table name (default: owl_user_preferences)
    user_identifier_method: getUserIdentifier  # Method on User entity (default: getUserIdentifier)
```

### Usage

#### 1. Define global settings

Create a service to declare your settings. Call it at application boot (e.g., via an EventSubscriber on `kernel.request` or in a CompilerPass):

```php
use OwlConcept\SettingsBundle\Builder\SettingsBuilder;

class SettingsConfigurator
{
    public function __construct(private SettingsBuilder $builder) {}

    public function configure(): void
    {
        $this->builder->createGroup('general', 'General Settings')
            ->setDescription('Main application configuration')
            ->setIcon('⚙️')
            ->add('app_name', 'Application Name', 'text', 'My CRM')
            ->add('default_language', 'Default Language', 'select', 'en', [
                'options' => ['en' => 'English', 'fr' => 'Français', 'es' => 'Español']
            ])
            ->add('items_per_page', 'Items per Page', 'number', 25, [
                'min' => 5, 'max' => 100
            ])
            ->add('maintenance_mode', 'Maintenance Mode', 'boolean', false, [
                'help' => 'Enables the maintenance page for visitors'
            ])
            ->build();

        $this->builder->createGroup('email', 'Email Configuration')
            ->setIcon('📧')
            ->setRole('ROLE_ADMIN')
            ->add('from_name', 'Sender Name', 'text', 'My CRM')
            ->add('from_email', 'Sender Email', 'email', 'noreply@mycrm.com')
            ->add('signature', 'Signature', 'textarea', '')
            ->build();

        $this->builder->createGroup('appearance', 'Appearance')
            ->setIcon('🎨')
            ->add('primary_color', 'Primary Color', 'color', '#0d6efd')
            ->add('logo', 'Logo', 'file', null, ['accept' => 'image/*'])
            ->build();
    }
}
```

#### 2. Define user preferences

```php
$this->builder->createUserPreferences()
    ->add('theme', 'Theme', 'select', 'light', [
        'options' => ['light' => 'Light', 'dark' => 'Dark', 'auto' => 'Automatic (system)']
    ])
    ->add('language', 'Language', 'select', null, [
        'options' => ['en' => 'English', 'fr' => 'Français'],
        'inherit_from' => 'general.default_language',
    ])
    ->add('items_per_page', 'Items per Page', 'number', null, [
        'min' => 5, 'max' => 100,
        'inherit_from' => 'general.items_per_page',
    ])
    ->add('notifications_email', 'Email Notifications', 'boolean', true)
    ->add('sidebar_collapsed', 'Sidebar Collapsed', 'boolean', false)
    ->build();
```

> When `inherit_from` is set and the user hasn't chosen a value, the preference automatically inherits from the corresponding global setting.

#### 3. Display the pages

**Admin page — Global settings:**

```twig
{% extends 'admin/base.html.twig' %}

{% block body %}
    <h1>Settings</h1>
    {% include '@OwlSettings/admin.html.twig' %}
{% endblock %}
```

**Profile page — User preferences:**

```twig
{% extends 'base.html.twig' %}

{% block body %}
    <h1>My Preferences</h1>
    {% include '@OwlSettings/user_preferences.html.twig' %}
{% endblock %}
```

> Forms are auto-generated with the correct field types, pre-filled with current values, and saving is done via AJAX without page reload.

#### 4. Read values in code

```php
use OwlConcept\SettingsBundle\Service\SettingsService;

class MyService
{
    public function __construct(private SettingsService $settings) {}

    public function doSomething(): void
    {
        // Global setting
        $appName = $this->settings->get('general.app_name');         // 'My CRM'
        $perPage = $this->settings->get('general.items_per_page');   // 25 (int)
        $maintenance = $this->settings->get('general.maintenance_mode'); // false (bool)

        // User preference (current user)
        $theme = $this->settings->getUserPreference('theme');        // 'dark'
        $lang = $this->settings->getUserPreference('language');      // 'en' (inherited from global if not set)

        // Preference of a specific user
        $theme = $this->settings->getUserPreference('theme', $otherUser);
    }
}
```

#### 5. Read values in Twig

```twig
{# Global setting #}
<title>{{ owl_setting('general.app_name') }}</title>

{# User preference #}
<body class="theme-{{ owl_user_pref('theme') }}">

{# Conditional #}
{% if owl_setting('general.maintenance_mode') %}
    <div class="alert">Site under maintenance</div>
{% endif %}
```

#### 6. Modify values in code

```php
// Modify a global setting
$this->settings->set('general.app_name', 'New Name');

// Modify a user preference
$this->settings->setUserPreference('theme', 'dark');

// Reset a preference (back to inheritance/default)
$this->settings->resetUserPreference('language');
```

#### GroupBuilder options

```php
$this->builder->createGroup('security', 'Security')
    ->setIcon('🔒')                              // Icon in the tab
    ->setRole('ROLE_SUPER_ADMIN')                 // Restrict access
    ->setDescription('Security settings')         // Description under the title
    ->add(...)
    ->build();
```

### Field types

| Type | HTML Render | Available Options |
|------|-----------|-------------------|
| `text` | `<input type="text">` | `placeholder`, `maxlength`, `required` |
| `email` | `<input type="email">` | `placeholder`, `maxlength`, `required` |
| `number` | `<input type="number">` | `min`, `max`, `step` |
| `boolean` | Toggle switch (checkbox) | — |
| `select` | `<select>` | `options`: `['value' => 'Label', ...]` |
| `textarea` | `<textarea>` | `rows` (default: 4) |
| `file` | `<input type="file">` with image preview | `accept`: `'image/*'` |
| `color` | `<input type="color">` | — |
| `password` | `<input type="password">` | `placeholder`, `maxlength` |

### Preference inheritance

The inheritance system works in 3 levels:

```
1. User-defined value               → highest priority
2. Global setting (inherit_from)    → if user hasn't set a value
3. Default value (defaultValue)     → last resort
```

Concrete example:

```php
// Global setting
->add('items_per_page', '...', 'number', 25)    // default: 25

// User preference
->add('items_per_page', '...', 'number', null, [
    'inherit_from' => 'general.items_per_page',  // inherits from global
])
```

| Case | Value returned by `owl_user_pref('items_per_page')` |
|------|-----------------------------------------------------|
| User chose 50 | `50` |
| User hasn't chosen, admin set 30 globally | `30` (inherited) |
| User hasn't chosen, global not modified | `25` (default) |

### Twig functions

| Function | Description | Example |
|----------|-------------|---------|
| `owl_setting(key)` | Read a global setting | `{{ owl_setting('general.app_name') }}` |
| `owl_user_pref(key)` | Read a user preference | `{{ owl_user_pref('theme') }}` |
| `owl_settings_groups()` | List of groups (for custom templates) | `{% for g in owl_settings_groups() %}` |
| `owl_settings_all()` | Map of all global settings | `{% set all = owl_settings_all() %}` |
| `owl_user_pref_definitions()` | Preference definitions | `{% for d in owl_user_pref_definitions() %}` |
| `owl_user_pref_all()` | Map of all user preferences | `{% set prefs = owl_user_pref_all() %}` |

### Database schema

The bundle creates 2 tables via the `owl:settings:install` command:

**`owl_settings`** — Global settings

| Column | Type | Description |
|--------|------|-------------|
| `setting_key` | VARCHAR(255) PK | Qualified key (e.g., `general.app_name`) |
| `setting_value` | TEXT | Serialized value |
| `setting_group` | VARCHAR(100) | Group (e.g., `general`) |
| `setting_type` | VARCHAR(50) | Field type |
| `created_at` | DATETIME | Creation date |
| `updated_at` | DATETIME | Last modification |

**`owl_user_preferences`** — User preferences

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | VARCHAR(255) PK | User identifier |
| `pref_key` | VARCHAR(255) PK | Preference key |
| `pref_value` | TEXT | Serialized value |
| `created_at` | DATETIME | Creation date |
| `updated_at` | DATETIME | Last modification |

---

## License

Proprietary — Owl Concept
