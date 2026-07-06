# Translation Guide

This application is fully translatable using Laravel's localization features combined with `laravel-vue-i18n` for the frontend.

## Table of Contents
- [Overview](#overview)
- [Configuration](#configuration)
- [Backend Translations](#backend-translations)
- [Frontend Translations](#frontend-translations)
- [Language Switcher](#language-switcher)
- [Adding New Languages](#adding-new-languages)
- [Best Practices](#best-practices)

## Overview

The application supports multiple languages with:
- **Backend**: Laravel's built-in translation system
- **Frontend**: `laravel-vue-i18n` package for Vue/Inertia components
- **Supported Languages**: English (en), Arabic (ar)

## Configuration

### Supported Locales
Defined in `config/app.php`:
```php
'supported_locales' => ['en', 'ar'],
```

### Default Locale
Set in `.env`:
```
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
```

### Middleware
The `SetLocale` middleware automatically detects and sets the user's preferred language based on:
1. Query parameter (`?lang=ar`)
2. Session storage
3. User preference (if user implements `HasLocalePreference`)
4. Browser language
5. Default locale

## Backend Translations

### Using Translations in PHP

#### JSON Format (Recommended for Most Cases)
Translation files: `lang/en.json`, `lang/ar.json`

```php
// Using the __ helper
__('Welcome')  // Returns "Welcome" or "مرحباً" based on locale

// With parameters
__('Welcome to :name', ['name' => 'PayCan'])
```

#### PHP Arrays (For Organized Translations)
Translation files: `lang/en/messages.php`, `lang/ar/messages.php`

```php
// Using dot notation
__('messages.welcome')  // Returns translated message

// With parameters
__('messages.profile_updated')
```

### Example Controller
```php
use Illuminate\Support\Facades\App;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        // Update profile logic...

        return back()->with('success', __('Profile updated successfully.'));
    }
}
```

### Changing Locale Programmatically
```php
use Illuminate\Support\Facades\App;

App::setLocale('ar');
```

## Frontend Translations

### Using Translations in Vue Components

#### Import and Use `trans()`
```vue
<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';

const greeting = trans('Welcome');
</script>

<template>
    <h1>{{ trans('Welcome') }}</h1>
    <p>{{ trans('Welcome to :name', { name: 'PayCan' }) }}</p>
</template>
```

#### Using the Composable
```vue
<script setup lang="ts">
import { useLocale } from '@/composables/useLocale';

const { trans, currentLocale, setLocale } = useLocale();
</script>

<template>
    <div>
        <p>Current language: {{ currentLocale }}</p>
        <button @click="setLocale('ar')">Switch to Arabic</button>
        <h1>{{ trans('Welcome') }}</h1>
    </div>
</template>
```

## Language Switcher

### Using the LanguageSwitcher Component
```vue
<script setup lang="ts">
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
</script>

<template>
    <div>
        <LanguageSwitcher />
    </div>
</template>
```

### Custom Language Switch Button
```vue
<script setup lang="ts">
import { useLocale } from '@/composables/useLocale';

const { setLocale } = useLocale();
</script>

<template>
    <button @click="setLocale('ar')">العربية</button>
    <button @click="setLocale('en')">English</button>
</template>
```

## Adding New Languages

### 1. Create Translation Files

#### JSON File (lang/fr.json)
```json
{
    "Welcome": "Bienvenue",
    "Dashboard": "Tableau de bord"
}
```

#### PHP Array (lang/fr/messages.php)
```php
<?php

return [
    'welcome' => 'Bienvenue à :name',
    'profile_updated' => 'Profil mis à jour avec succès.',
];
```

### 2. Update Configuration
Add the new locale to `config/app.php`:
```php
'supported_locales' => ['en', 'ar', 'fr'],
```

### 3. Update Locale Names
Add the language name in `resources/js/composables/useLocale.ts`:
```typescript
const getLocaleName = (locale: string): string => {
    const localeNames: Record<string, string> = {
        en: 'English',
        ar: 'العربية',
        fr: 'Français',  // Add this
    };
    return localeNames[locale] || locale;
};
```

### 4. Publish Laravel's Language Files (Optional)
```bash
php artisan lang:publish
```
Then copy and translate files from `lang/en/` to `lang/fr/`.

## Best Practices

### 1. Use JSON for Simple Strings
```json
{
    "Save": "Save",
    "Cancel": "Cancel"
}
```

### 2. Use PHP Arrays for Organized Groups
```php
// lang/en/auth.php
return [
    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many login attempts.',
];
```

### 3. Always Use Translation Functions
❌ Bad:
```vue
<button>Save</button>
```

✅ Good:
```vue
<button>{{ trans('Save') }}</button>
```

### 4. Use Parameters for Dynamic Content
```php
__('Welcome to :name', ['name' => $appName])
```

```vue
{{ trans('Welcome to :name', { name: appName }) }}
```

### 5. Keep Keys Descriptive
❌ Bad:
```json
{
    "msg1": "Profile updated",
    "msg2": "Settings saved"
}
```

✅ Good:
```json
{
    "Profile updated successfully": "Profile updated successfully",
    "Settings saved successfully": "Settings saved successfully"
}
```

### 6. Test All Languages
After adding translations, test the application in each language to ensure:
- All text is translated
- UI layout works with longer/shorter text
- RTL support for Arabic

### 7. RTL Support for Arabic
The application should automatically handle RTL layout for Arabic. Ensure CSS respects text direction:
```css
/* Tailwind handles most of this automatically */
.container {
    direction: rtl; /* Applied automatically for ar locale */
}
```

## API Reference

### Backend

#### Translation Helpers
- `__('key')` - Get translation
- `trans('key')` - Same as `__()`
- `trans_choice('key', count)` - Pluralization
- `App::setLocale('en')` - Set locale
- `App::getLocale()` - Get current locale

### Frontend

#### Composable: `useLocale()`
- `currentLocale` - Current language code
- `availableLocales` - Array of supported locales
- `setLocale(locale)` - Switch language
- `getLocaleName(locale)` - Get language display name
- `trans(key, params)` - Translate string

#### Direct Import
```typescript
import { trans, wTrans } from 'laravel-vue-i18n';

trans('Welcome');
wTrans('Welcome', 2); // Pluralization
```

## Troubleshooting

### Translations Not Loading
1. Clear config cache: `php artisan config:clear`
2. Rebuild frontend: `npm run build`
3. Check JSON file syntax

### Locale Not Changing
1. Check session storage
2. Verify middleware is registered
3. Clear browser cookies/cache

### Missing Translations
1. Fallback locale will be used
2. Check console for warnings
3. Verify translation key exists in JSON/PHP files
