# Settings Code Snippets

Quick copy-paste snippets for common settings usage patterns.

## Basic Reading

```php
// Single setting with default
$value = settings('stripe.enabled', false);

// Without default (may return null)
$value = settings('stripe.api_key');

// Get all settings in a group
$stripeSettings = app(SettingsManager::class)->getByGroup('stripe');
```

## Stripe Settings

```php
// Check if Stripe is enabled
if (settings('stripe.enabled', false)) {
    // Initialize Stripe
    $stripe = new \Stripe\StripeClient(settings('stripe.api_key'));
}

// Get all Stripe settings
$apiKey = settings('stripe.api_key');
$publishableKey = settings('stripe.publishable_key');
$webhookSecret = settings('stripe.webhook_secret');
$supportsSubscriptions = settings('stripe.enable_subscriptions', true);

// Use in a service
class StripePaymentService
{
    protected ?\Stripe\StripeClient $stripe = null;

    public function __construct()
    {
        if (settings('stripe.enabled', false)) {
            $this->stripe = new \Stripe\StripeClient(
                settings('stripe.api_key')
            );
        }
    }

    public function isAvailable(): bool
    {
        return $this->stripe !== null;
    }

    public function supportsSubscriptions(): bool
    {
        return $this->isAvailable() &&
               settings('stripe.enable_subscriptions', true);
    }
}
```

## PayPal Settings

```php
// Check if PayPal is enabled
if (settings('paypal.enabled', false)) {
    $clientId = settings('paypal.client_id');
    $clientSecret = settings('paypal.client_secret');
    $mode = settings('paypal.mode', 'sandbox'); // 'sandbox' or 'live'
}

// PayPal service example
class PayPalPaymentService
{
    protected array $config;

    public function __construct()
    {
        $this->config = [
            'mode' => settings('paypal.mode', 'sandbox'),
            'client_id' => settings('paypal.client_id'),
            'client_secret' => settings('paypal.client_secret'),
        ];
    }

    public function isLiveMode(): bool
    {
        return settings('paypal.mode') === 'live';
    }

    public function supportsSubscriptions(): bool
    {
        return settings('paypal.enable_subscriptions', true);
    }
}
```

## Download Link Settings

```php
// Check if downloader is enabled
if (!settings('downloader.enabled', true)) {
    throw new Exception('Digital downloads are disabled');
}

// Generate download link
class DownloadLinkGenerator
{
    public function generate(Order $order, Product $product): array
    {
        $expiryHours = settings('downloader.link_expiry', 48);
        $maxDownloads = settings('downloader.max_downloads', 5);

        $token = Str::random(64);

        $link = DownloadLink::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'token' => $token,
            'expires_at' => now()->addHours($expiryHours),
            'max_downloads' => $maxDownloads,
            'downloads_count' => 0,
        ]);

        return [
            'url' => route('download.file', ['token' => $token]),
            'expires_at' => $link->expires_at,
            'max_downloads' => $maxDownloads,
        ];
    }

    public function validate(string $token): bool
    {
        $link = DownloadLink::where('token', $token)->firstOrFail();

        if ($link->expires_at < now()) {
            return false;
        }

        $maxDownloads = settings('downloader.max_downloads', 5);
        if ($link->downloads_count >= $maxDownloads) {
            return false;
        }

        return true;
    }
}
```

## License Generator Settings

```php
// Generate license key
class LicenseKeyGenerator
{
    public function generate(): string
    {
        if (!settings('license_generator.enabled', true)) {
            throw new Exception('License generation is disabled');
        }

        $length = settings('license_generator.key_length', 16);
        $prefix = settings('license_generator.prefix', '');

        $key = strtoupper(Str::random($length));

        return $prefix ? $prefix . $key : $key;
    }
}

// Usage
$licenseKey = app(LicenseKeyGenerator::class)->generate();
// Example output: "PROD-A1B2C3D4E5F6G7H8" (if prefix is "PROD-")
```

## Mail Settings

```php
// Configure mail dynamically from settings
class MailConfigurationService
{
    public function configure(): void
    {
        config([
            'mail.default' => settings('mail.mailer', 'smtp'),
            'mail.from.address' => settings('mail.from_address'),
            'mail.from.name' => settings('mail.from_name'),
        ]);

        // SMTP configuration
        if (settings('mail.mailer') === 'smtp') {
            config([
                'mail.mailers.smtp.host' => settings('mail.host'),
                'mail.mailers.smtp.port' => settings('mail.port', 587),
                'mail.mailers.smtp.encryption' => settings('mail.encryption', 'tls'),
                'mail.mailers.smtp.username' => settings('mail.username'),
                'mail.mailers.smtp.password' => settings('mail.password'),
            ]);
        }

        // Mailgun configuration
        if (settings('mail.mailer') === 'mailgun') {
            config([
                'services.mailgun.domain' => settings('mail.mailgun_domain'),
                'services.mailgun.secret' => settings('mail.mailgun_secret'),
                'services.mailgun.endpoint' => settings('mail.mailgun_endpoint', 'api.mailgun.net'),
            ]);
        }
    }
}

// In a middleware or service provider
app(MailConfigurationService::class)->configure();
```

## Notification Settings

```php
// Check if specific notification is enabled
class NotificationService
{
    public function sendOrderConfirmation(Order $order): void
    {
        if (!settings('notifications.order_confirmation', true)) {
            return;
        }

        $subject = settings('notifications.order_confirmation_subject', 'Order Confirmation');
        $body = settings('notifications.order_confirmation_body', '');

        Mail::to($order->customer->email)->send(
            new OrderConfirmationMail($order, $subject, $body)
        );
    }

    public function sendSubscriptionCreated(Subscription $subscription): void
    {
        if (!settings('notifications.subscription_created', true)) {
            return;
        }

        $subject = settings('notifications.subscription_created_subject');
        $body = settings('notifications.subscription_created_body');

        // Replace template variables
        $subject = $this->replaceVariables($subject, $subscription);
        $body = $this->replaceVariables($body, $subscription);

        Mail::to($subscription->user->email)->send(
            new SubscriptionCreatedMail($subscription, $subject, $body)
        );
    }

    protected function replaceVariables(string $template, $data): string
    {
        return str_replace([
            '{{customer_name}}',
            '{{plan_name}}',
            '{{amount}}',
        ], [
            $data->user->name,
            $data->plan->name,
            $data->amount,
        ], $template);
    }
}
```

## Application Settings

```php
// Get app settings
$appName = settings('app.name', config('app.name'));
$appUrl = settings('app.url', config('app.url'));
$appTimezone = settings('app.timezone', config('app.timezone'));
$appLocale = settings('app.locale', config('app.locale'));
$apiKey = settings('app.api_key'); // For admin API

// Use in service
class ApplicationService
{
    public function getConfig(): array
    {
        return [
            'name' => settings('app.name'),
            'url' => settings('app.url'),
            'timezone' => settings('app.timezone'),
            'locale' => settings('app.locale'),
        ];
    }

    public function isValidApiKey(string $key): bool
    {
        return hash_equals(settings('app.api_key'), $key);
    }
}
```

## Social Login Settings

```php
// Check if social provider is enabled
class SocialLoginService
{
    public function getEnabledProviders(): array
    {
        $providers = [];

        if (settings('social.google_enabled', false)) {
            $providers[] = 'google';
        }

        if (settings('social.facebook_enabled', false)) {
            $providers[] = 'facebook';
        }

        if (settings('social.github_enabled', false)) {
            $providers[] = 'github';
        }

        return $providers;
    }

    public function configureSocialite(): void
    {
        // Google
        if (settings('social.google_enabled', false)) {
            config([
                'services.google.client_id' => settings('social.google_client_id'),
                'services.google.client_secret' => settings('social.google_client_secret'),
                'services.google.redirect' => settings('social.google_redirect'),
            ]);
        }

        // Facebook
        if (settings('social.facebook_enabled', false)) {
            config([
                'services.facebook.client_id' => settings('social.facebook_client_id'),
                'services.facebook.client_secret' => settings('social.facebook_client_secret'),
                'services.facebook.redirect' => settings('social.facebook_redirect'),
            ]);
        }

        // GitHub
        if (settings('social.github_enabled', false)) {
            config([
                'services.github.client_id' => settings('social.github_client_id'),
                'services.github.client_secret' => settings('social.github_client_secret'),
                'services.github.redirect' => settings('social.github_redirect'),
            ]);
        }
    }
}
```

## Check Multiple Payment Gateways

```php
class PaymentGatewayService
{
    public function getAvailableGateways(): array
    {
        $gateways = [];

        if (settings('stripe.enabled', false)) {
            $gateways['stripe'] = [
                'name' => 'Stripe',
                'supports_subscriptions' => settings('stripe.enable_subscriptions', true),
                'publishable_key' => settings('stripe.publishable_key'),
            ];
        }

        if (settings('paypal.enabled', false)) {
            $gateways['paypal'] = [
                'name' => 'PayPal',
                'supports_subscriptions' => settings('paypal.enable_subscriptions', true),
                'mode' => settings('paypal.mode', 'sandbox'),
            ];
        }

        return $gateways;
    }

    public function hasAnyGatewayEnabled(): bool
    {
        return settings('stripe.enabled', false) ||
               settings('paypal.enabled', false);
    }

    public function getPreferredGateway(): ?string
    {
        if (settings('stripe.enabled', false)) {
            return 'stripe';
        }

        if (settings('paypal.enabled', false)) {
            return 'paypal';
        }

        return null;
    }
}
```

## Middleware Example

```php
use Closure;
use Illuminate\Http\Request;

class EnsurePaymentGatewayEnabled
{
    public function handle(Request $request, Closure $next, string $gateway)
    {
        $enabled = settings("{$gateway}.enabled", false);

        if (!$enabled) {
            return redirect()
                ->back()
                ->with('error', ucfirst($gateway) . ' is not available at this time.');
        }

        return $next($request);
    }
}

// Usage in routes:
Route::post('/payment/stripe', [PaymentController::class, 'stripe'])
    ->middleware('gateway:stripe');

Route::post('/payment/paypal', [PaymentController::class, 'paypal'])
    ->middleware('gateway:paypal');
```

## View Composer Example

```php
use Illuminate\View\View;

class SettingsComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'appName' => settings('app.name'),
            'availableGateways' => $this->getAvailableGateways(),
            'socialLoginEnabled' => $this->getSocialLoginProviders(),
        ]);
    }

    protected function getAvailableGateways(): array
    {
        return collect([
            'stripe' => settings('stripe.enabled', false),
            'paypal' => settings('paypal.enabled', false),
        ])->filter()->keys()->toArray();
    }

    protected function getSocialLoginProviders(): array
    {
        return collect([
            'google' => settings('social.google_enabled', false),
            'facebook' => settings('social.facebook_enabled', false),
            'github' => settings('social.github_enabled', false),
        ])->filter()->keys()->toArray();
    }
}

// Register in AppServiceProvider
View::composer('layouts.app', SettingsComposer::class);
```

## Testing Example

```php
use App\Services\Settings\SettingsManager;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set test settings
        $manager = app(SettingsManager::class);
        $manager->set('stripe.enabled', true, 'boolean', true);
        $manager->set('stripe.api_key', 'sk_test_123', 'encrypted', false);
        $manager->set('stripe.publishable_key', 'pk_test_123', 'string', true);
    }

    public function test_stripe_payment_processing()
    {
        $this->assertTrue(settings('stripe.enabled'));
        $this->assertEquals('sk_test_123', settings('stripe.api_key'));

        // Test payment processing...
    }
}
```

## API Usage Example

```php
// In an API controller
class SettingsApiController extends Controller
{
    public function index()
    {
        // Only return public settings
        $manager = app(SettingsManager::class);

        return response()->json([
            'payment_gateways' => [
                'stripe' => [
                    'enabled' => settings('stripe.enabled', false),
                    'publishable_key' => settings('stripe.publishable_key'),
                    'supports_subscriptions' => settings('stripe.enable_subscriptions', true),
                ],
                'paypal' => [
                    'enabled' => settings('paypal.enabled', false),
                    'mode' => settings('paypal.mode', 'sandbox'),
                    'supports_subscriptions' => settings('paypal.enable_subscriptions', true),
                ],
            ],
            'app' => [
                'name' => settings('app.name'),
                'url' => settings('app.url'),
                'timezone' => settings('app.timezone'),
                'locale' => settings('app.locale'),
            ],
        ]);
    }
}
```

## Caching Settings for Performance

```php
class CachedSettingsService
{
    protected array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = settings($key, $default);
        }

        return $this->cache[$key];
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}

// Usage in high-traffic areas
$cached = app(CachedSettingsService::class);
$stripeEnabled = $cached->get('stripe.enabled', false);
```
