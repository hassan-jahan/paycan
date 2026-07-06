# Settings Group Structure

This document outlines the clean, intuitive group naming structure for all settings in the application.

## Design Principle

**Group names should be simple, descriptive, and independent of the UI structure.**

- ✅ **Good**: `stripe.enabled`, `downloader.max_downloads`
- ❌ **Bad**: `fulfillment_providers.downloader_enabled`, `payment_gateways.stripe_enabled`

## Complete Settings Structure

### Payment Gateways

#### Stripe (`stripe.*`)
```php
stripe.enabled                  // boolean  - Enable Stripe gateway
stripe.api_key                  // encrypted - Secret API key
stripe.publishable_key          // string   - Public key for frontend
stripe.enable_subscriptions     // boolean  - Support recurring payments
stripe.webhook_secret           // encrypted - Webhook signing secret
```

**Usage:**
```php
if (settings('stripe.enabled', false)) {
    $stripe = new StripeClient(settings('stripe.api_key'));
}
```

#### PayPal (`paypal.*`)
```php
paypal.enabled                  // boolean  - Enable PayPal gateway
paypal.mode                     // string   - 'sandbox' or 'live'
paypal.client_id                // string   - PayPal REST API client ID
paypal.client_secret            // encrypted - PayPal REST API client secret
paypal.enable_subscriptions     // boolean  - Support recurring payments
paypal.webhook_id               // string   - Webhook verification ID
```

**Usage:**
```php
if (settings('paypal.enabled', false)) {
    $mode = settings('paypal.mode', 'sandbox');
    $clientId = settings('paypal.client_id');
}
```

---

### Fulfillment Services

#### Digital Downloader (`downloader.*`)
```php
downloader.enabled              // boolean  - Enable download link generation
downloader.link_expiry          // integer  - Hours before link expires (default: 48)
downloader.max_downloads        // integer  - Max downloads per link (default: 5)
```

**Usage:**
```php
if (settings('downloader.enabled', true)) {
    $expiryHours = settings('downloader.link_expiry', 48);
    $maxDownloads = settings('downloader.max_downloads', 5);

    $link = DownloadLink::create([
        'expires_at' => now()->addHours($expiryHours),
        'max_downloads' => $maxDownloads,
    ]);
}
```

#### License Generator (`license_generator.*`)
```php
license_generator.enabled       // boolean  - Enable license key generation
license_generator.key_length    // integer  - Length of generated keys (default: 16)
license_generator.prefix        // string   - Optional prefix (e.g., "PROD-")
```

**Usage:**
```php
if (settings('license_generator.enabled', true)) {
    $length = settings('license_generator.key_length', 16);
    $prefix = settings('license_generator.prefix', '');

    $key = $prefix . strtoupper(Str::random($length));
}
```

---

### Application Settings

#### App (`app.*`)
```php
app.name                        // string   - Application name
app.url                         // string   - Base URL
app.timezone                    // string   - Default timezone
app.locale                      // string   - Default language (en, ar)
app.api_key                     // encrypted - Admin API secret key
```

**Usage:**
```php
$appName = settings('app.name', config('app.name'));
$apiKey = settings('app.api_key');
```

---

### Email Configuration

#### Mail Settings (`mail.*`)
```php
mail.mailer                     // string   - Driver: smtp, mailgun, ses, postmark, resend, sendmail
mail.from_address               // string   - Default sender email
mail.from_name                  // string   - Default sender name

// SMTP
mail.host                       // string   - SMTP server hostname
mail.port                       // integer  - SMTP port (587, 465)
mail.encryption                 // string   - 'tls', 'ssl', or ''
mail.username                   // string   - SMTP username
mail.password                   // encrypted - SMTP password

// Mailgun
mail.mailgun_domain             // string   - Mailgun domain
mail.mailgun_secret             // encrypted - Mailgun API key
mail.mailgun_endpoint           // string   - API endpoint (default: api.mailgun.net)

// Amazon SES
mail.ses_key                    // string   - AWS access key ID
mail.ses_secret                 // encrypted - AWS secret access key
mail.ses_region                 // string   - AWS region (default: us-east-1)
mail.ses_configuration_set      // string   - Optional configuration set

// Postmark
mail.postmark_token             // encrypted - Postmark API token
mail.postmark_message_stream_id // string   - Message stream (default: outbound)

// Resend
mail.resend_key                 // encrypted - Resend API key

// Sendmail
mail.sendmail_path              // string   - Path to sendmail binary
```

**Usage:**
```php
config([
    'mail.default' => settings('mail.mailer', 'smtp'),
    'mail.from.address' => settings('mail.from_address'),
    'mail.mailers.smtp.host' => settings('mail.host'),
]);
```

---

### Notifications & Templates

#### Notifications (`notifications.*`)
```php
// Admin notifications
notifications.admin_email                           // string   - Admin email address
notifications.notify_admin_new_order                // boolean  - Notify on new orders
notifications.notify_admin_failed_payment           // boolean  - Notify on failed payments

// Order confirmation
notifications.order_confirmation                    // boolean  - Enable order confirmation emails
notifications.order_confirmation_subject            // string   - Email subject template
notifications.order_confirmation_body               // text     - Rich editor body
notifications.order_confirmation_body_html          // text     - Raw HTML override

// Order fulfilled
notifications.order_fulfilled                       // boolean  - Enable fulfillment emails
notifications.order_fulfilled_subject               // string   - Email subject template
notifications.order_fulfilled_body                  // text     - Rich editor body
notifications.order_fulfilled_body_html             // text     - Raw HTML override

// Subscription created
notifications.subscription_created                  // boolean  - Enable subscription created emails
notifications.subscription_created_subject          // string   - Email subject template
notifications.subscription_created_body             // text     - Rich editor body
notifications.subscription_created_body_html        // text     - Raw HTML override

// Subscription renewed
notifications.subscription_renewed                  // boolean  - Enable renewal emails
notifications.subscription_renewed_subject          // string   - Email subject template
notifications.subscription_renewed_body             // text     - Rich editor body
notifications.subscription_renewed_body_html        // text     - Raw HTML override

// Subscription cancelled
notifications.subscription_cancelled                // boolean  - Enable cancellation emails
notifications.subscription_cancelled_subject        // string   - Email subject template
notifications.subscription_cancelled_body           // text     - Rich editor body
notifications.subscription_cancelled_body_html      // text     - Raw HTML override

// Payment failed
notifications.payment_failed                        // boolean  - Enable payment failure emails
notifications.payment_failed_subject                // string   - Email subject template
notifications.payment_failed_body                   // text     - Rich editor body
notifications.payment_failed_body_html              // text     - Raw HTML override
```

**Usage:**
```php
if (settings('notifications.order_confirmation', true)) {
    $subject = settings('notifications.order_confirmation_subject');
    $body = settings('notifications.order_confirmation_body');

    Mail::to($order->customer->email)->send(
        new OrderConfirmationMail($order, $subject, $body)
    );
}
```

---

### Social Login

#### Social (`social.*`)
```php
// Google OAuth
social.google_enabled           // boolean  - Enable Google login
social.google_client_id         // string   - Google OAuth client ID
social.google_client_secret     // encrypted - Google OAuth client secret
social.google_redirect          // string   - OAuth callback URL

// Facebook OAuth
social.facebook_enabled         // boolean  - Enable Facebook login
social.facebook_client_id       // string   - Facebook app ID
social.facebook_client_secret   // encrypted - Facebook app secret
social.facebook_redirect        // string   - OAuth callback URL

// GitHub OAuth
social.github_enabled           // boolean  - Enable GitHub login
social.github_client_id         // string   - GitHub OAuth client ID
social.github_client_secret     // encrypted - GitHub OAuth client secret
social.github_redirect          // string   - OAuth callback URL
```

**Usage:**
```php
$enabledProviders = collect([
    'google' => settings('social.google_enabled', false),
    'facebook' => settings('social.facebook_enabled', false),
    'github' => settings('social.github_enabled', false),
])->filter()->keys();
```

---

## Group Naming Rules

### ✅ DO

1. **Use simple, descriptive names**
   - `stripe.enabled` (not `payment_providers.stripe_enabled`)
   - `downloader.max_downloads` (not `fulfillment_providers.downloader_max_downloads`)

2. **Group by service/feature, not by UI section**
   - Group = `stripe` (the service)
   - Not Group = `payment_gateways` (the UI section)

3. **Keep consistent with industry standards**
   - `mail.mailer`, `mail.host` (like Laravel's config)
   - `stripe.api_key`, `paypal.client_id` (like official SDKs)

4. **Use singular form for service names**
   - `downloader.enabled` (not `downloaders.enabled`)
   - `license_generator.prefix` (not `license_generators.prefix`)

### ❌ DON'T

1. **Don't nest groups unnecessarily**
   - ❌ `fulfillment_providers.downloader_enabled`
   - ✅ `downloader.enabled`

2. **Don't use UI structure as groups**
   - ❌ `payment_providers_settings.stripe_enabled`
   - ✅ `stripe.enabled`

3. **Don't mix service and setting in the key**
   - ❌ `stripe_api_key` (group: settings)
   - ✅ `stripe.api_key` (group: stripe)

4. **Don't use abbreviations unless standard**
   - ❌ `dl.max_downloads`
   - ✅ `downloader.max_downloads`

---

## Adding New Settings Groups

When adding a new settings group, follow these steps:

### 1. Create a Settings Provider

```php
// app/Services/Settings/Providers/NewServiceSettingsProvider.php
class NewServiceSettingsProvider implements SettingProvider
{
    public function getGroup(): string
    {
        return 'new_service'; // Simple, descriptive group name
    }

    public function getSchema(): Schema
    {
        return Schema::make()
            ->components([
                Section::make('New Service Configuration')
                    ->schema([
                        // Use group__key pattern for form fields
                        Toggle::make('new_service__enabled'),
                        TextInput::make('new_service__api_key'),
                    ])
            ]);
    }

    public function getDefaults(): array
    {
        return [
            'enabled' => false,  // Keys without group prefix
            'api_key' => '',
        ];
    }
}
```

### 2. Register in Settings Page

```php
// In the appropriate settings page
protected function getProviders(): array
{
    return [
        app(NewServiceSettingsProvider::class),
        // ... other providers
    ];
}
```

### 3. Use in Code

```php
// Access with simple dot notation
if (settings('new_service.enabled', false)) {
    $apiKey = settings('new_service.api_key');
}
```

---

## Migration from Old Structure

If you have existing settings with the old nested structure:

```sql
-- Old structure
UPDATE settings SET key = 'downloader.enabled'
WHERE key = 'fulfillment_providers.downloader_enabled';

UPDATE settings SET key = 'downloader.link_expiry'
WHERE key = 'fulfillment_providers.downloader_link_expiry';

UPDATE settings SET key = 'downloader.max_downloads'
WHERE key = 'fulfillment_providers.downloader_max_downloads';

UPDATE settings SET key = 'license_generator.enabled'
WHERE key = 'fulfillment_providers.license_generator_enabled';

UPDATE settings SET key = 'license_generator.key_length'
WHERE key = 'fulfillment_providers.license_generator_key_length';

UPDATE settings SET key = 'license_generator.prefix'
WHERE key = 'fulfillment_providers.license_generator_prefix';
```

---

## Summary

### Current Group Structure

| Group | Purpose | Examples |
|-------|---------|----------|
| `app` | Application settings | `app.name`, `app.api_key` |
| `stripe` | Stripe payment gateway | `stripe.enabled`, `stripe.api_key` |
| `paypal` | PayPal payment gateway | `paypal.enabled`, `paypal.client_id` |
| `downloader` | Digital download links | `downloader.enabled`, `downloader.link_expiry` |
| `license_generator` | License key generation | `license_generator.enabled`, `license_generator.key_length` |
| `mail` | Email configuration | `mail.mailer`, `mail.host` |
| `notifications` | Email notifications | `notifications.order_confirmation`, `notifications.admin_email` |
| `social` | Social login OAuth | `social.google_enabled`, `social.facebook_client_id` |

### Quick Reference

```php
// Payment Gateways
settings('stripe.enabled', false)
settings('paypal.mode', 'sandbox')

// Fulfillment
settings('downloader.enabled', true)
settings('license_generator.key_length', 16)

// Application
settings('app.name')
settings('app.api_key')

// Email
settings('mail.mailer', 'smtp')
settings('mail.host')

// Notifications
settings('notifications.order_confirmation', true)

// Social Login
settings('social.google_enabled', false)
```
