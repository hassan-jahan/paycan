# New Settings Structure - Complete Guide

## 🎯 Overview

We've refactored the settings system to use **flat, service-based group names** instead of nested hierarchies. Each service now has its own simple, intuitive group name.

## ✨ Key Changes

### Before (Nested/Inconsistent):
```php
❌ mail.mailgun_domain           // Service nested under 'mail'
❌ mail.postmark_token            // Service nested under 'mail'
❌ social.google_enabled          // Service nested under 'social'
❌ fulfillment_providers.downloader_enabled  // Too nested
```

### After (Flat/Consistent):
```php
✅ mailgun.domain                 // Direct service group
✅ postmark.token                 // Direct service group
✅ google.enabled                 // Direct service group
✅ downloader.enabled             // Simple and clean
```

---

## 📋 Complete Settings Structure

### Category: `payments`

#### Stripe (`stripe.*`)
```php
stripe.enabled                    // boolean
stripe.api_key                    // encrypted
stripe.publishable_key            // string
stripe.enable_subscriptions       // boolean
stripe.webhook_secret             // encrypted
```

**Usage:**
```php
if (settings('stripe.enabled', false)) {
    $stripe = new StripeClient(settings('stripe.api_key'));
}
```

#### PayPal (`paypal.*`)
```php
paypal.enabled                    // boolean
paypal.mode                       // string (sandbox/live)
paypal.client_id                  // string
paypal.client_secret              // encrypted
paypal.enable_subscriptions       // boolean
paypal.webhook_id                 // string
```

**Usage:**
```php
if (settings('paypal.enabled', false)) {
    $mode = settings('paypal.mode', 'sandbox');
}
```

---

### Category: `email`

#### Email Config (`email.*`) - Shared Settings
```php
email.default_provider            // string (smtp/mailgun/postmark/ses/resend/sendmail)
email.from_address                // string
email.from_name                   // string
```

**Usage:**
```php
$provider = settings('email.default_provider', 'smtp');
$fromAddress = settings('email.from_address');
```

#### SMTP (`smtp.*`)
```php
smtp.host                         // string
smtp.port                         // integer
smtp.encryption                   // string (tls/ssl/'')
smtp.username                     // string
smtp.password                     // encrypted
```

**Usage:**
```php
if (settings('email.default_provider') === 'smtp') {
    config([
        'mail.mailers.smtp.host' => settings('smtp.host'),
        'mail.mailers.smtp.port' => settings('smtp.port'),
    ]);
}
```

#### Mailgun (`mailgun.*`)
```php
mailgun.domain                    // string
mailgun.api_key                   // encrypted
mailgun.endpoint                  // string
```

**Usage:**
```php
if (settings('email.default_provider') === 'mailgun') {
    config([
        'services.mailgun.domain' => settings('mailgun.domain'),
        'services.mailgun.secret' => settings('mailgun.api_key'),
    ]);
}
```

#### Postmark (`postmark.*`)
```php
postmark.token                    // encrypted
postmark.stream_id                // string
```

**Usage:**
```php
config([
    'services.postmark.token' => settings('postmark.token'),
    'services.postmark.message_stream_id' => settings('postmark.stream_id'),
]);
```

#### Amazon SES (`ses.*`)
```php
ses.access_key                    // string
ses.secret_key                    // encrypted
ses.region                        // string
ses.configuration_set             // string
```

**Usage:**
```php
config([
    'services.ses.key' => settings('ses.access_key'),
    'services.ses.secret' => settings('ses.secret_key'),
    'services.ses.region' => settings('ses.region'),
]);
```

#### Resend (`resend.*`)
```php
resend.api_key                    // encrypted
```

**Usage:**
```php
config(['services.resend.key' => settings('resend.api_key')]);
```

#### Sendmail (`sendmail.*`)
```php
sendmail.path                     // string
```

**Usage:**
```php
config(['mail.mailers.sendmail.path' => settings('sendmail.path')]);
```

---

### Category: `auth`

#### Google OAuth (`google.*`)
```php
google.enabled                    // boolean
google.client_id                  // string
google.client_secret              // encrypted
google.redirect                   // string
```

**Usage:**
```php
if (settings('google.enabled', false)) {
    config([
        'services.google.client_id' => settings('google.client_id'),
        'services.google.client_secret' => settings('google.client_secret'),
    ]);
}
```

#### Facebook OAuth (`facebook.*`)
```php
facebook.enabled                  // boolean
facebook.client_id                // string
facebook.client_secret            // encrypted
facebook.redirect                 // string
```

**Usage:**
```php
if (settings('facebook.enabled', false)) {
    config([
        'services.facebook.client_id' => settings('facebook.client_id'),
        'services.facebook.client_secret' => settings('facebook.client_secret'),
    ]);
}
```

#### GitHub OAuth (`github.*`)
```php
github.enabled                    // boolean
github.client_id                  // string
github.client_secret              // encrypted
github.redirect                   // string
```

**Usage:**
```php
if (settings('github.enabled', false)) {
    config([
        'services.github.client_id' => settings('github.client_id'),
        'services.github.client_secret' => settings('github.client_secret'),
    ]);
}
```

---

### Category: `fulfillment`

#### Digital Downloader (`downloader.*`)
```php
downloader.enabled                // boolean
downloader.link_expiry            // integer (hours)
downloader.max_downloads          // integer
```

**Usage:**
```php
if (settings('downloader.enabled', true)) {
    $expiryHours = settings('downloader.link_expiry', 48);
    $maxDownloads = settings('downloader.max_downloads', 5);
}
```

#### License Generator (`license_generator.*`)
```php
license_generator.enabled         // boolean
license_generator.key_length      // integer
license_generator.prefix          // string
```

**Usage:**
```php
if (settings('license_generator.enabled', true)) {
    $length = settings('license_generator.key_length', 16);
    $prefix = settings('license_generator.prefix', '');
}
```

---

### Category: `app`

#### Application (`app.*`)
```php
app.name                          // string
app.url                           // string
app.timezone                      // string
app.locale                        // string
app.api_key                       // encrypted
```

**Usage:**
```php
$appName = settings('app.name');
$apiKey = settings('app.api_key');
```

---

### Category: `notifications`

#### Notifications (`notifications.*`)
```php
notifications.admin_email                         // string
notifications.notify_admin_new_order              // boolean
notifications.notify_admin_failed_payment         // boolean
notifications.order_confirmation                  // boolean
notifications.order_confirmation_subject          // string
notifications.order_confirmation_body             // text
notifications.order_fulfilled                     // boolean
notifications.subscription_created                // boolean
notifications.payment_failed                      // boolean
// ... etc
```

**Usage:**
```php
if (settings('notifications.order_confirmation', true)) {
    $subject = settings('notifications.order_confirmation_subject');
    $body = settings('notifications.order_confirmation_body');
}
```

---

## 🗂️ Settings Providers Structure

Each service has its own provider:

```
app/Services/Settings/Providers/
├── AppSettingsProvider.php              (app.*)
├── EmailConfigProvider.php              (email.*)
├── SmtpSettingsProvider.php             (smtp.*)
├── MailgunSettingsProvider.php          (mailgun.*)
├── PostmarkSettingsProvider.php         (postmark.*)
├── SesSettingsProvider.php              (ses.*)
├── ResendSettingsProvider.php           (resend.*)
├── SendmailSettingsProvider.php         (sendmail.*)
├── GoogleAuthProvider.php               (google.*)
├── FacebookAuthProvider.php             (facebook.*)
├── GithubAuthProvider.php               (github.*)
├── DownloaderSettingsProvider.php       (downloader.*)
├── LicenseGeneratorSettingsProvider.php (license_generator.*)
├── NotificationSettingsProvider.php     (notifications.*)
└── ...

app/Services/Payment/Gateways/
├── StripeSettingsProvider.php           (stripe.*)
└── PayPalSettingsProvider.php           (paypal.*)
```

---

## 🔄 Migration Guide

### Step 1: Deploy New Code
Deploy all the new settings providers and pages.

### Step 2: Run Migration Script
```bash
# Backup your database first!
mysqldump -u username -p database_name settings > settings_backup.sql

# Run the migration
mysql -u username -p database_name < database/migrations/migrate_settings_to_flat_groups.sql
```

### Step 3: Clear Settings Cache
```php
php artisan tinker
>>> app(\App\Services\Settings\SettingsManager::class)->clearCache();
```

### Step 4: Verify Settings
Check the Filament admin panel to ensure all settings loaded correctly.

---

## 📊 Comparison Table

| Old Key | New Key | Type | Category |
|---------|---------|------|----------|
| `mail.mailer` | `email.default_provider` | string | email |
| `mail.from_address` | `email.from_address` | string | email |
| `mail.host` | `smtp.host` | string | email |
| `mail.mailgun_domain` | `mailgun.domain` | string | email |
| `mail.postmark_token` | `postmark.token` | encrypted | email |
| `mail.ses_key` | `ses.access_key` | string | email |
| `mail.resend_key` | `resend.api_key` | encrypted | email |
| `social.google_enabled` | `google.enabled` | boolean | auth |
| `social.facebook_client_id` | `facebook.client_id` | string | auth |
| `social.github_client_secret` | `github.client_secret` | encrypted | auth |
| `fulfillment_providers.downloader_enabled` | `downloader.enabled` | boolean | fulfillment |
| `fulfillment_providers.license_generator_prefix` | `license_generator.prefix` | string | fulfillment |

---

## 🎨 Benefits

### 1. **Consistency**
Every service follows the same pattern: `service.setting`

### 2. **Simplicity**
```php
// Old: Too nested
settings('mail.mailgun_domain')
settings('social.google_client_id')

// New: Clean and simple
settings('mailgun.domain')
settings('google.client_id')
```

### 3. **Intuitive**
Group names match service names developers already know:
- `stripe.*` - everyone knows Stripe
- `mailgun.*` - everyone knows Mailgun
- `google.*` - everyone knows Google

### 4. **Scalable**
Adding a new email provider? Just create `NewProviderSettingsProvider` with group `newprovider`.

### 5. **Type Safety**
```php
// Clear what service you're configuring
$mailgunKey = settings('mailgun.api_key');  // Obviously Mailgun

// vs the old way
$mailgunKey = settings('mail.mailgun_secret');  // mail? mailgun? secret? api_key?
```

---

## 🚀 Code Examples

### Email Configuration Service
```php
class EmailConfigurationService
{
    public function configure(): void
    {
        // Get the active provider
        $provider = settings('email.default_provider', 'smtp');

        // Configure general settings
        config([
            'mail.default' => $provider,
            'mail.from.address' => settings('email.from_address'),
            'mail.from.name' => settings('email.from_name'),
        ]);

        // Configure provider-specific settings
        match ($provider) {
            'smtp' => $this->configureSmtp(),
            'mailgun' => $this->configureMailgun(),
            'postmark' => $this->configurePostmark(),
            'ses' => $this->configureSes(),
            'resend' => $this->configureResend(),
            'sendmail' => $this->configureSendmail(),
            default => null,
        };
    }

    protected function configureSmtp(): void
    {
        config([
            'mail.mailers.smtp.host' => settings('smtp.host'),
            'mail.mailers.smtp.port' => settings('smtp.port'),
            'mail.mailers.smtp.encryption' => settings('smtp.encryption'),
            'mail.mailers.smtp.username' => settings('smtp.username'),
            'mail.mailers.smtp.password' => settings('smtp.password'),
        ]);
    }

    protected function configureMailgun(): void
    {
        config([
            'services.mailgun.domain' => settings('mailgun.domain'),
            'services.mailgun.secret' => settings('mailgun.api_key'),
            'services.mailgun.endpoint' => settings('mailgun.endpoint'),
        ]);
    }
}
```

### Social Auth Service
```php
class SocialAuthService
{
    public function getEnabledProviders(): array
    {
        return collect([
            'google' => settings('google.enabled', false),
            'facebook' => settings('facebook.enabled', false),
            'github' => settings('github.enabled', false),
        ])->filter()->keys()->toArray();
    }

    public function configureSocialite(): void
    {
        if (settings('google.enabled', false)) {
            config([
                'services.google.client_id' => settings('google.client_id'),
                'services.google.client_secret' => settings('google.client_secret'),
                'services.google.redirect' => settings('google.redirect'),
            ]);
        }

        if (settings('facebook.enabled', false)) {
            config([
                'services.facebook.client_id' => settings('facebook.client_id'),
                'services.facebook.client_secret' => settings('facebook.client_secret'),
                'services.facebook.redirect' => settings('facebook.redirect'),
            ]);
        }

        if (settings('github.enabled', false)) {
            config([
                'services.github.client_id' => settings('github.client_id'),
                'services.github.client_secret' => settings('github.client_secret'),
                'services.github.redirect' => settings('github.redirect'),
            ]);
        }
    }
}
```

---

## 📝 Summary

The new flat structure makes settings:
- ✅ **More intuitive**: `mailgun.domain` vs `mail.mailgun_domain`
- ✅ **Easier to read**: `google.enabled` vs `social.google_enabled`
- ✅ **Simpler to use**: Direct service names
- ✅ **Consistent**: Every service follows the same pattern
- ✅ **Scalable**: Easy to add new services

This matches industry standards and how developers naturally think about configuring services!
