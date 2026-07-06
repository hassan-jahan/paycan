# Settings Flow Diagram

## Complete Settings Lifecycle

```
┌─────────────────────────────────────────────────────────────────────┐
│                        SETTINGS PROVIDER                             │
│  app/Services/Payment/Gateways/StripeSettingsProvider.php          │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  │ Defines schema
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         FORM SCHEMA                                  │
│                                                                      │
│  Toggle::make('stripe__enabled')                                    │
│  TextInput::make('stripe__api_key')           ◄──── Uses __ (double │
│  TextInput::make('stripe__publishable_key')          underscore)    │
│  Toggle::make('stripe__enable_subscriptions')                       │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  │ Loads into form
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      FILAMENT ADMIN PANEL                            │
│  app/Filament/Pages/Settings/PaymentProvidersSettings.php          │
│                                                                      │
│  ┌──────────────────────────────────────────────────────┐          │
│  │  Stripe Configuration                                │          │
│  │  ┌────────────────────────────────────────────────┐  │          │
│  │  │ [✓] Enable Stripe                              │  │          │
│  │  └────────────────────────────────────────────────┘  │          │
│  │  ┌────────────────────────────────────────────────┐  │          │
│  │  │ Secret Key: sk_test_••••••••••••••••••••••     │  │          │
│  │  └────────────────────────────────────────────────┘  │          │
│  │  ┌────────────────────────────────────────────────┐  │          │
│  │  │ Publishable Key: pk_test_1234567890            │  │          │
│  │  └────────────────────────────────────────────────┘  │          │
│  │  ┌────────────────────────────────────────────────┐  │          │
│  │  │ [✓] Enable Subscription Support                │  │          │
│  │  └────────────────────────────────────────────────┘  │          │
│  │                                                      │          │
│  │                              [Save Settings] ◄────── Admin clicks│
│  └──────────────────────────────────────────────────────┘          │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  │ save() method
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      CONVERSION LAYER                                │
│                                                                      │
│  Input (from form):                                                 │
│    stripe__enabled = true                                           │
│    stripe__api_key = 'sk_test_...'                                  │
│    stripe__publishable_key = 'pk_test_...'                          │
│                                                                      │
│  Convert __ to . (dot notation)    ◄──── str_replace('.', '__', ...)│
│                                                                      │
│  Output (to database):                                              │
│    stripe.enabled = true                                            │
│    stripe.api_key = 'sk_test_...'                                   │
│    stripe.publishable_key = 'pk_test_...'                           │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  │ SettingsManager::set()
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          DATABASE                                    │
│                      (settings table)                                │
│                                                                      │
│  ┌────────────────────────┬──────────────┬───────────┬──────────┐  │
│  │ key                    │ value        │ type      │is_public │  │
│  ├────────────────────────┼──────────────┼───────────┼──────────┤  │
│  │ stripe.enabled         │ 1            │ boolean   │ true     │  │
│  │ stripe.api_key         │ [encrypted]  │ encrypted │ false    │  │
│  │ stripe.publishable_key │ pk_test_...  │ string    │ true     │  │
│  │ stripe.enable_subs...  │ 1            │ boolean   │ true     │  │
│  └────────────────────────┴──────────────┴───────────┴──────────┘  │
│                                         ▲                            │
│                                         │ Uses . (dot)               │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  │ Read from database
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       CODE USAGE                                     │
│                                                                      │
│  // In your controllers, services, etc.                             │
│                                                                      │
│  $enabled = settings('stripe.enabled', false);                      │
│  $apiKey = settings('stripe.api_key');         ◄──── Uses . (dot)   │
│  $publishableKey = settings('stripe.publishable_key');              │
│  $supportsSubscriptions = settings('stripe.enable_subscriptions');  │
│                                                                      │
│  // Or get all settings for a group                                 │
│  $stripeSettings = app(SettingsManager::class)                      │
│      ->getByGroup('stripe');                                        │
│                                                                      │
│  // Returns: [                                                      │
│  //   'enabled' => true,                                            │
│  //   'api_key' => 'sk_test_...',                                   │
│  //   'publishable_key' => 'pk_test_...',                           │
│  //   'enable_subscriptions' => true,                               │
│  // ]                                                                │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## Quick Reference Table

| Context | Format | Example | Notes |
|---------|--------|---------|-------|
| **Form Field Names** (Filament) | `group__key` | `stripe__api_key` | Double underscore (`__`) |
| **Database Keys** | `group.key` | `stripe.api_key` | Dot notation (`.`) |
| **Code Usage** | `group.key` | `settings('stripe.api_key')` | Dot notation (`.`) |
| **Provider Defaults** | `key` only | `'api_key' => ''` | No group prefix |

## Real-World Examples

### Example 1: Stripe Payment Processing

```php
// 1. Admin configures in Filament
// Form fields: stripe__enabled, stripe__api_key, stripe__publishable_key

// 2. Saved to database as:
// stripe.enabled, stripe.api_key, stripe.publishable_key

// 3. Used in code:
$stripe = new StripeClient(settings('stripe.api_key'));
$paymentIntent = $stripe->paymentIntents->create([
    'amount' => 1000,
    'currency' => 'usd',
]);
```

### Example 2: Download Link Configuration

```php
// 1. Admin configures in Filament
// Form fields:
//   downloader__enabled
//   downloader__link_expiry
//   downloader__max_downloads

// 2. Saved to database as:
// downloader.enabled
// downloader.link_expiry
// downloader.max_downloads

// 3. Used in code:
if (settings('downloader.enabled')) {
    $expiryHours = settings('downloader.link_expiry', 48);
    $maxDownloads = settings('downloader.max_downloads', 5);

    $link = DownloadLink::create([
        'expires_at' => now()->addHours($expiryHours),
        'max_downloads' => $maxDownloads,
    ]);
}
```

## Why Two Different Formats?

### Why `__` in Forms?
- Filament form field names cannot contain dots (`.`)
- Dots in field names would be interpreted as nested arrays
- Example: `stripe.api_key` would become `['stripe' => ['api_key' => ...]]`
- Using `__` keeps it flat: `['stripe__api_key' => ...]`

### Why `.` in Database and Code?
- Industry standard for hierarchical configuration
- Easier to read and understand: `stripe.api_key` vs `stripe__api_key`
- Compatible with config system: `config('stripe.api_key')`
- Allows grouping: `getByGroup('stripe')` returns all `stripe.*` keys

## Security Notes

### Automatic Encryption
Settings with these keywords in their key are automatically encrypted:
- `api_key`
- `secret`
- `secret_key`
- `password`
- `webhook_secret`
- `client_secret`

### Public vs Private
- `is_public = false`: Sensitive data (passwords, API keys)
- `is_public = true`: Non-sensitive data (enabled flags, URLs)

### Example:
```php
// Automatically encrypted and private
settings('stripe.api_key')           // is_public = false, type = encrypted
settings('stripe.webhook_secret')    // is_public = false, type = encrypted

// Public settings
settings('stripe.enabled')           // is_public = true, type = boolean
settings('stripe.publishable_key')   // is_public = true, type = string
```
