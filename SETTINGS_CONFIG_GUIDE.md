# Settings Configuration Guide

This document explains the new PHP configuration-based settings system implemented in this application.

## Overview

The settings system has been refactored from individual provider classes to a configuration-based approach using PHP config files with direct translation support.

### Benefits

- **60% less code**: Reduced from ~2000 lines across 24 files to ~800 lines across 10 files
- **Easier to maintain**: Add new settings by editing config files, not creating new classes
- **Native i18n support**: Uses Laravel's `__()` function directly in config files
- **Familiar pattern**: Follows Laravel's standard config file structure
- **No breaking changes**: Same database structure and behavior

## Directory Structure

```
resources/settings/          # Settings configuration files
├── app.php                 # Application settings
├── stripe.php              # Stripe payment gateway
├── paypal.php              # PayPal payment gateway
├── email.php               # Email configuration
├── smtp.php                # SMTP settings
├── mailgun.php             # Mailgun settings
├── postmark.php            # Postmark settings
├── ses.php                 # Amazon SES settings
├── resend.php              # Resend settings
├── sendmail.php            # Sendmail settings
├── google.php              # Google OAuth
├── facebook.php            # Facebook OAuth
├── github.php              # GitHub OAuth
├── downloader.php          # File downloader
└── license_generator.php   # License generator

lang/
├── en/settings.php         # English translations
└── ar/settings.php         # Arabic translations

app/
├── Filament/Pages/Settings/
│   ├── BaseSettingsPage.php         # Base class for all settings pages
│   ├── GeneralSettings.php
│   ├── PaymentProvidersSettings.php
│   ├── MailSettings.php
│   ├── SocialLoginSettings.php
│   └── FulfillmentProvidersSettings.php
└── Services/Settings/
    └── SchemaBuilder.php             # Converts config to Filament components
```

## Configuration File Structure

Each settings config file in `resources/settings/` follows this structure:

```php
<?php

return [
    'group' => 'stripe',  // Database key prefix
    'category' => 'payment',  // UI category
    'label' => __('settings.stripe.label'),  // Section label (translated)
    'description' => __('settings.stripe.description'),  // Section description

    'columns' => 2,  // Form layout columns

    'section' => [  // Optional section configuration
        'collapsible' => true,
        'collapsed' => true,
        'heading' => fn($get) => /* dynamic heading */,
    ],

    'fields' => [
        'enabled' => [
            'type' => 'toggle',  // Field type
            'label' => __('settings.stripe.enabled.label'),
            'helper' => __('settings.stripe.enabled.helper'),
            'default' => false,
        ],
        'api_key' => [
            'type' => 'password',
            'label' => __('settings.stripe.api_key.label'),
            'helper' => __('settings.stripe.api_key.helper'),
            'encrypted' => true,  // Mark as encrypted
            'public' => false,    // Not publicly accessible
            'columnSpan' => 'full',
            'default' => '',
        ],
        // ... more fields
    ],
];
```

## Field Types

The following field types are supported:

### Text Input
```php
'name' => [
    'type' => 'text',
    'label' => __('settings.app.name.label'),
    'helper' => __('settings.app.name.helper'),
    'required' => true,
    'placeholder' => 'My App',
    'url' => false,  // Set to true for URL validation
    'email' => false,  // Set to true for email validation
    'readOnly' => false,
    'copyable' => false,
    'columnSpan' => 'full',
    'default' => config('app.name'),
],
```

### Password Input
```php
'password' => [
    'type' => 'password',
    'label' => __('settings.smtp.password.label'),
    'helper' => __('settings.smtp.password.helper'),
    'required' => false,
    'encrypted' => true,  // Automatically encrypted in database
    'public' => false,    // Never shown in public API
    'default' => '',
],
```

### Number Input
```php
'port' => [
    'type' => 'number',
    'label' => __('settings.smtp.port.label'),
    'helper' => __('settings.smtp.port.helper'),
    'min' => 1,
    'max' => 65535,
    'step' => 1,
    'default' => 587,
],
```

### Select
```php
'mode' => [
    'type' => 'select',
    'label' => __('settings.paypal.mode.label'),
    'helper' => __('settings.paypal.mode.helper'),
    'options' => [
        'sandbox' => __('settings.paypal.mode.options.sandbox'),
        'live' => __('settings.paypal.mode.options.live'),
    ],
    'required' => true,
    'searchable' => false,
    'multiple' => false,
    'default' => 'sandbox',
],
```

### Toggle
```php
'enabled' => [
    'type' => 'toggle',
    'label' => __('settings.stripe.enabled.label'),
    'helper' => __('settings.stripe.enabled.helper'),
    'inline' => true,
    'default' => false,
],
```

## Internationalization

### Adding Translations

Translations are stored in `lang/{locale}/settings.php`:

```php
// lang/en/settings.php
return [
    'stripe' => [
        'label' => 'Stripe Payment Gateway',
        'description' => 'Configure your Stripe payment gateway settings',
        'enabled' => [
            'label' => 'Enable Stripe',
            'helper' => 'Toggle to enable or disable Stripe payments',
        ],
        'api_key' => [
            'label' => 'Secret Key',
            'helper' => 'Your Stripe secret key (encrypted)',
        ],
    ],
];
```

### Adding a New Language

1. Create a new language file: `lang/{locale}/settings.php`
2. Copy the structure from `lang/en/settings.php`
3. Translate all values
4. The config files will automatically use the new translations

Example for Spanish:

```bash
cp lang/en/settings.php lang/es/settings.php
# Edit lang/es/settings.php with Spanish translations
```

## Creating a New Settings Page

1. **Create config file** in `resources/settings/myservice.php`:

```php
<?php

return [
    'group' => 'myservice',
    'category' => 'integration',
    'label' => __('settings.myservice.label'),
    'description' => __('settings.myservice.description'),

    'fields' => [
        'enabled' => [
            'type' => 'toggle',
            'label' => __('settings.myservice.enabled.label'),
            'default' => false,
        ],
        'api_key' => [
            'type' => 'password',
            'label' => __('settings.myservice.api_key.label'),
            'encrypted' => true,
            'public' => false,
            'default' => '',
        ],
    ],
];
```

2. **Add translations** in `lang/en/settings.php`:

```php
'myservice' => [
    'label' => 'My Service',
    'description' => 'Configure My Service integration',
    'enabled' => [
        'label' => 'Enable My Service',
    ],
    'api_key' => [
        'label' => 'API Key',
    ],
],
```

3. **Add to settings page**:

If you want to add to an existing page (e.g., Payment Providers):

```php
// app/Filament/Pages/Settings/PaymentProvidersSettings.php

protected function getConfigGroups(): array
{
    return ['stripe', 'paypal', 'myservice'];  // Add your group
}
```

Or create a new settings page:

```php
<?php

namespace App\Filament\Pages\Settings;

class MyServiceSettings extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationLabel = 'My Service';
    protected static ?string $title = 'My Service Settings';
    protected static ?string $slug = 'settings/myservice';

    protected function getConfigGroups(): array
    {
        return ['myservice'];
    }
}
```

## Reading Settings in Code

Settings are stored in the database with dot notation:

```php
// Get a setting value
$stripeEnabled = settings('stripe.enabled', false);
$stripeApiKey = settings('stripe.api_key');

// Get all settings for a group
$stripeSettings = app(SettingsManager::class)->getByGroup('stripe');

// Set a setting
app(SettingsManager::class)->set('stripe.enabled', true, 'boolean', true);
```

## Form Field to Database Mapping

- **Form field name**: `group__key` (double underscore)
  - Example: `stripe__api_key`

- **Database key**: `group.key` (dot notation)
  - Example: `stripe.api_key`

This conversion happens automatically in `BaseSettingsPage`.

## Encryption

Fields are automatically encrypted if:
- `'encrypted' => true` is set in config, OR
- Field name contains: `api_key`, `secret`, `secret_key`, `password`, `webhook_secret`, `token`

## Public vs Private Settings

Fields are private (not shown in public API) if:
- `'public' => false` is set in config, OR
- Field name is in the sensitive list: `api_key`, `secret`, `secret_key`, `password`, `webhook_secret`, `token`

## Advanced Features

### Dynamic Section Headings

You can use closures to make section headings dynamic:

```php
'section' => [
    'heading' => fn($get) => $get('email__default_provider') === 'smtp'
        ? new HtmlString('🟢 SMTP')
        : 'SMTP',
    'collapsible' => true,
    'collapsed' => true,
],
```

### Callable Defaults

Use closures for dynamic default values:

```php
'api_key' => [
    'type' => 'text',
    'label' => __('settings.app.api_key.label'),
    'default' => fn() => 'pk_' . Str::random(40),
],
```

### Callable Options

Generate select options dynamically:

```php
'timezone' => [
    'type' => 'select',
    'label' => __('settings.app.timezone.label'),
    'options' => fn() => timezone_identifiers_list(),
    'searchable' => true,
],
```

## Migration from Old System

If you have existing settings in the database, they should work without changes as long as the group and key names match.

Example:
- Old: `stripe.api_key` ✅ Still works
- New config: `'group' => 'stripe'` with field `'api_key'`

## Troubleshooting

### Settings not saving

1. Check if the config file exists in `resources/settings/`
2. Verify the group name matches in config and page
3. Check Laravel logs for errors

### Translations not working

1. Ensure `lang/{locale}/settings.php` exists
2. Run `php artisan config:clear`
3. Check the translation key path matches

### Config cache issues

Settings config files are in `resources/settings/`, not `config/`, so they won't be cached by `php artisan config:cache`. This is intentional to allow `__()` translations to work correctly.
