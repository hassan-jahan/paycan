<x-filament-panels::page>
    <style>
        .demo-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 1024px) {
            .demo-section {
                grid-template-columns: 1fr;
            }
        }
        .demo-code {
            position: relative;
        }
        .demo-code pre {
            margin: 0;
            padding: 1rem;
            background: rgb(31 41 55);
            border-radius: 0.5rem;
            overflow-x: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .demo-code code {
            color: rgb(243 244 246);
        }
        .dark .demo-code pre {
            background: rgb(17 24 39);
        }
        .copy-button {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            background: rgb(55 65 81);
            color: white;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .copy-button:hover {
            background: rgb(75 85 99);
        }
        .copy-button.copied {
            background: rgb(34 197 94);
        }
        .auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .auth-badge.authenticated {
            background: rgb(220 252 231);
            color: rgb(22 101 52);
        }
        .dark .auth-badge.authenticated {
            background: rgb(6 78 59);
            color: rgb(167 243 208);
        }
        .auth-badge.guest {
            background: rgb(243 244 246);
            color: rgb(75 85 99);
        }
        .dark .auth-badge.guest {
            background: rgb(55 65 81);
            color: rgb(209 213 219);
        }
        .auth-indicator {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 9999px;
            background-color: currentColor;
        }
    </style>

    @if (!$productId)
    <x-filament::section>
        <x-slot name="heading">
            No Products Available
        </x-slot>
        <x-slot name="description">
            Please create at least one active product with prices to see all demo features.
        </x-slot>
    </x-filament::section>
    @endif

    <!-- Quick Setup -->
    <x-filament::section collapsible>
        <x-slot name="heading">
            Quick Setup Guide
        </x-slot>
        <x-slot name="description">
            Follow these steps to integrate PayCan SDK into your application.
        </x-slot>

        <div class="space-y-6">
            <div class="demo-section">
                <div>
                    <h3 class="text-base font-semibold mb-2">Step 1: Include SDK</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Add the SDK to your HTML page.</p>
                </div>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'setup-1')">Copy</button>
                    <pre id="setup-1"><code>&lt;script type="module"&gt;
  import { PayCan } from '/sdk/paycan-sdk.js';

  const paycan = new PayCan({
    apiUrl: '{{ config('app.url') }}',
    debug: true
  });

  window.paycan = paycan;
&lt;/script&gt;</code></pre>
                </div>
            </div>

            <div class="demo-section">
                <div>
                    <h3 class="text-base font-semibold mb-2">Step 2: Get Token from Backend</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Never expose your API secret in frontend! Always get tokens from your backend.</p>
                </div>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'setup-2')">Copy</button>
                    <pre id="setup-2"><code>// Backend endpoint (Express.js example)
app.post('/api/paycan/token', requireAuth, async (req, res) => {
  const userId = req.session.userId;
  const user = await User.findById(userId);

  const response = await fetch('{{ config('app.url') }}/api/admin/users/sync', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': process.env.PAYCAN_API_SECRET
    },
    body: JSON.stringify({
      user_id: user.id,
      user: { name: user.name, email: user.email }
    })
  });

  const data = await response.json();
  res.json({ token: data.token });
});</code></pre>
                </div>
            </div>

            <div class="demo-section">
                <div>
                    <h3 class="text-base font-semibold mb-2">Step 3: Set Token</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Initialize SDK with user token on app load.</p>
                </div>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'setup-3')">Copy</button>
                    <pre id="setup-3"><code>// On app load
async function initPayCan() {
  const response = await fetch('/api/paycan/token');
  const { token } = await response.json();
  paycan.setUserToken(token);
}

initPayCan();</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>

    <!-- Authentication -->
    <x-filament::section collapsible>
        <x-slot name="heading">
            Authentication Management
        </x-slot>
        <x-slot name="description">
            Manage SDK authentication state for testing.
        </x-slot>

        <div class="demo-section">
            <div>
                <p class="text-sm font-medium mb-2">Current Status:</p>
                <span id="authStatusBadge" class="auth-badge guest">
                    <span class="auth-indicator"></span>
                    <span>Guest Mode</span>
                </span>
                <div id="userInfoDisplay" style="display: none;" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Authenticated as: <strong>{{ $demoUser->email }}</strong>
                </div>

                <div class="mt-4">
                    <p class="text-sm font-medium mb-2">Actions:</p>
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button id="loginButton" onclick="loginAsDemo(); return false;">
                            Login as Demo User
                        </x-filament::button>
                        <x-filament::button id="logoutButton" onclick="logoutDemo(); return false;" color="danger" disabled>
                            Logout
                        </x-filament::button>
                        <x-filament::button onclick="checkAuthStatus(); return false;" color="gray">
                            Check Status
                        </x-filament::button>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium mb-2">Code:</p>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'auth-code')">Copy</button>
                    <pre id="auth-code"><code>// Login
paycan.setUserToken(token);

// Logout
paycan.logout();

// Check status
paycan.isAuthenticated(); // boolean</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>

    @if ($productId)
    <!-- Checkout - Guest -->
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            Checkout Modal - Guest
        </x-slot>
        <x-slot name="description">
            Test checkout flow for unauthenticated users.
        </x-slot>

        <div class="demo-section">
            <div>
                <p class="text-sm font-medium mb-2">Product Checkout:</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Opens modal showing all prices.</p>
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-filament::button onclick="openGuestCheckoutProduct('light'); return false;">
                        Light
                    </x-filament::button>
                    <x-filament::button onclick="openGuestCheckoutProduct('dark'); return false;" color="gray">
                        Dark
                    </x-filament::button>
                </div>

                <p class="text-sm font-medium mb-2 mt-6">Specific Price:</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Direct checkout for specific price.</p>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button onclick="openGuestCheckoutPrice('light'); return false;">
                        Light
                    </x-filament::button>
                    <x-filament::button onclick="openGuestCheckoutPrice('dark'); return false;" color="gray">
                        Dark
                    </x-filament::button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium mb-2">Code:</p>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'checkout-guest')">Copy</button>
                    <pre id="checkout-guest"><code>// Guest checkout works without authentication
// The SDK will show email field automatically
paycan.openCheckoutModal({{ $productId }}, {
  theme: 'light',
  onSuccess: (url) => {
    window.location.href = url;
  }
});

paycan.openCheckoutModalPrice({{ $priceId }}, {
  theme: 'dark'
});</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>

    <!-- Checkout - Authenticated -->
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            Checkout Modal - Authenticated
        </x-slot>
        <x-slot name="description">
            Test checkout flow for authenticated users.
        </x-slot>

        <div class="demo-section">
            <div>
                <div id="authCheckoutWarning" class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200 rounded-lg text-sm" style="display: none;">
                    Please login first using the Authentication section.
                </div>

                <p class="text-sm font-medium mb-2">Product Checkout:</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Email is auto-filled from token.</p>
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-filament::button class="auth-required" onclick="openAuthCheckoutProduct('light'); return false;" disabled>
                        Light
                    </x-filament::button>
                    <x-filament::button class="auth-required" onclick="openAuthCheckoutProduct('dark'); return false;" color="gray" disabled>
                        Dark
                    </x-filament::button>
                </div>

                <p class="text-sm font-medium mb-2 mt-6">Specific Price:</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Direct checkout with authentication.</p>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button class="auth-required" onclick="openAuthCheckoutPrice('light'); return false;" disabled>
                        Light
                    </x-filament::button>
                    <x-filament::button class="auth-required" onclick="openAuthCheckoutPrice('dark'); return false;" color="gray" disabled>
                        Dark
                    </x-filament::button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium mb-2">Code:</p>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'checkout-auth')">Copy</button>
                    <pre id="checkout-auth"><code>paycan.setUserToken(token);

paycan.openCheckoutModal({{ $productId }}, {
  theme: 'light',
  onSuccess: (url) => window.location.href = url
});</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>

    <!-- Products Modal -->
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            Products Modal
        </x-slot>
        <x-slot name="description">
            Browse all available products and plans.
        </x-slot>

        <div class="demo-section">
            <div>
                <p class="text-sm font-medium mb-2">All Products:</p>
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-filament::button onclick="openProductsModal('light'); return false;">
                        Light
                    </x-filament::button>
                    <x-filament::button onclick="openProductsModal('dark'); return false;" color="gray">
                        Dark
                    </x-filament::button>
                </div>

                <p class="text-sm font-medium mb-2 mt-6">Filtered:</p>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button onclick="openProductsModalFiltered('subscription'); return false;" color="info">
                        Subscriptions
                    </x-filament::button>
                    <x-filament::button onclick="openProductsModalFiltered('digital'); return false;" color="success">
                        Digital
                    </x-filament::button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium mb-2">Code:</p>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'products')">Copy</button>
                    <pre id="products"><code>paycan.openProductsModal({
  theme: 'light',
  onProductSelected: (p) => console.log(p)
});

paycan.openProductsModal({
  type: 'subscription',
  theme: 'dark'
});</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>
    @endif

    <!-- Subscriptions -->
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            Subscriptions Modal
        </x-slot>
        <x-slot name="description">
            View and manage user subscriptions (requires authentication).
        </x-slot>

        <div class="demo-section">
            <div>
                <div id="subsWarning" class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200 rounded-lg text-sm" style="display: none;">
                    Please login first.
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">View subscriptions with status and billing info.</p>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button class="auth-required" onclick="openSubscriptionsModal('light'); return false;" disabled>
                        Light
                    </x-filament::button>
                    <x-filament::button class="auth-required" onclick="openSubscriptionsModal('dark'); return false;" color="gray" disabled>
                        Dark
                    </x-filament::button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium mb-2">Code:</p>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'subs')">Copy</button>
                    <pre id="subs"><code>import { SubscriptionsModal } from '/sdk/paycan-sdk.js';

const modal = new SubscriptionsModal(paycan, {
  theme: 'light'
});
modal.open();</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>

    <!-- Orders -->
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            Orders Modal
        </x-slot>
        <x-slot name="description">
            View user order history (requires authentication).
        </x-slot>

        <div class="demo-section">
            <div>
                <div id="ordersWarning" class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200 rounded-lg text-sm" style="display: none;">
                    Please login first.
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">View orders with payment details and downloads.</p>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button class="auth-required" onclick="openOrdersModal('light'); return false;" disabled>
                        Light
                    </x-filament::button>
                    <x-filament::button class="auth-required" onclick="openOrdersModal('dark'); return false;" color="gray" disabled>
                        Dark
                    </x-filament::button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium mb-2">Code:</p>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'orders')">Copy</button>
                    <pre id="orders"><code>import { OrdersModal } from '/sdk/paycan-sdk.js';

const modal = new OrdersModal(paycan, {
  theme: 'dark'
});
modal.open();</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>

    <!-- Transactions -->
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            Transactions Modal
        </x-slot>
        <x-slot name="description">
            View transaction history (requires authentication).
        </x-slot>

        <div class="demo-section">
            <div>
                <div id="transactionsWarning" class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-200 rounded-lg text-sm" style="display: none;">
                    Please login first.
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">View transactions with gateway info and status.</p>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button class="auth-required" onclick="openTransactionsModal('light'); return false;" disabled>
                        Light
                    </x-filament::button>
                    <x-filament::button class="auth-required" onclick="openTransactionsModal('dark'); return false;" color="gray" disabled>
                        Dark
                    </x-filament::button>
                </div>
            </div>

            <div>
                <p class="text-sm font-medium mb-2">Code:</p>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'transactions')">Copy</button>
                    <pre id="transactions"><code>import { TransactionsModal } from '/sdk/paycan-sdk.js';

const modal = new TransactionsModal(paycan, {
  theme: 'light'
});
modal.open();</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>

    <!-- API Reference -->
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">
            API Reference
        </x-slot>
        <x-slot name="description">
            Common SDK methods and usage examples.
        </x-slot>

        <div class="space-y-6">
            <div class="demo-section">
                <div>
                    <h3 class="text-base font-semibold mb-2">Authentication</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Set tokens, check status, logout.</p>
                </div>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'api-auth')">Copy</button>
                    <pre id="api-auth"><code>paycan.setUserToken(token);
paycan.isAuthenticated(); // boolean
paycan.getToken();
paycan.logout();
const { user } = await paycan.me();</code></pre>
                </div>
            </div>

            <div class="demo-section">
                <div>
                    <h3 class="text-base font-semibold mb-2">Orders API</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">List orders, get downloads, licenses.</p>
                </div>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'api-orders')">Copy</button>
                    <pre id="api-orders"><code>await paycan.orders.list({
  filter: { status: 'completed' },
  include: 'productPrice.product',
  sort: '-created_at'
});
await paycan.orders.get(orderId);
await paycan.orders.getDownloads(orderId);
await paycan.orders.getLicenses(orderId);</code></pre>
                </div>
            </div>

            <div class="demo-section">
                <div>
                    <h3 class="text-base font-semibold mb-2">Subscriptions API</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Manage subscriptions.</p>
                </div>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'api-subs')">Copy</button>
                    <pre id="api-subs"><code>await paycan.subscriptions.list({
  filter: { status: 'active' }
});
await paycan.subscriptions.get(id);
await paycan.subscriptions.cancel(id);
await paycan.subscriptions.resume(id);</code></pre>
                </div>
            </div>

            <div class="demo-section">
                <div>
                    <h3 class="text-base font-semibold mb-2">Products API</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">List and retrieve products.</p>
                </div>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'api-products')">Copy</button>
                    <pre id="api-products"><code>await paycan.products.list({
  filter: { type: 'subscription' },
  include: 'prices'
});
await paycan.products.get(id);</code></pre>
                </div>
            </div>

            <div class="demo-section">
                <div>
                    <h3 class="text-base font-semibold mb-2">Transactions API</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">View transactions.</p>
                </div>
                <div class="demo-code">
                    <button class="copy-button" onclick="copyCode(this, 'api-trans')">Copy</button>
                    <pre id="api-trans"><code>await paycan.transactions.list({
  filter: { status: 'succeeded' },
  sort: '-created_at'
});
await paycan.transactions.get(id);</code></pre>
                </div>
            </div>
        </div>
    </x-filament::section>

    <script type="module">
        import { PayCan, SubscriptionsModal, OrdersModal, TransactionsModal } from '/sdk/paycan-sdk.js?v={{ filemtime(public_path('sdk/paycan-sdk.js')) }}';

        const paycan = new PayCan({
            apiUrl: {{ Js::from(config('app.url')) }},
            debug: true
        });

        window.paycan = paycan;
        window.SubscriptionsModal = SubscriptionsModal;
        window.OrdersModal = OrdersModal;
        window.TransactionsModal = TransactionsModal;
        window.demoToken = {{ Js::from($token) }};
        window.demoProductId = {{ Js::from($productId) }};
        window.demoPriceId = {{ Js::from($priceId) }};

        console.log('SDK loaded');

        // Check authentication immediately after SDK initialization
        // The SDK automatically loads token from localStorage in constructor
        const isAuth = paycan.isAuthenticated();
        console.log('Initial auth status:', isAuth);

        // Trigger UI update once DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof window.updateAuthUI === 'function') {
                    window.updateAuthUI(isAuth);
                    if (isAuth) {
                        console.log('Restored authentication from localStorage');
                    }
                }
            });
        } else {
            // DOM already loaded, wait a bit for the regular script to define updateAuthUI
            setTimeout(() => {
                if (typeof window.updateAuthUI === 'function') {
                    window.updateAuthUI(isAuth);
                    if (isAuth) {
                        console.log('Restored authentication from localStorage');
                    }
                }
            }, 100);
        }
    </script>

    <script>
        function copyCode(btn, id) {
            const code = document.getElementById(id).textContent;
            navigator.clipboard.writeText(code).then(() => {
                const orig = btn.textContent;
                btn.textContent = "Copied!";
                btn.classList.add("copied");
                setTimeout(() => {
                    btn.textContent = orig;
                    btn.classList.remove("copied");
                }, 2000);
            });
        }

        function showMsg(msg, type) {
            if (typeof Filament !== "undefined" && Filament.Notification) {
                const notif = new Filament.Notification();
                notif.title(msg).duration(3000);
                if (type === "success") notif.success();
                else if (type === "error") notif.danger();
                else notif.info();
                notif.send();
            }
        }

        function loginAsDemo() {
            if (!window.paycan) {
                showMsg("SDK loading...", "error");
                return;
            }
            window.paycan.setUserToken(window.demoToken);
            updateAuthUI(true);
            showMsg("Logged in!", "success");
        }

        function logoutDemo() {
            if (!window.paycan) return;
            window.paycan.logout();
            updateAuthUI(false);
            showMsg("Logged out!", "success");
        }

        function checkAuthStatus() {
            if (!window.paycan) {
                showMsg("SDK loading...", "error");
                return;
            }
            const isAuth = window.paycan.isAuthenticated();
            showMsg(isAuth ? "Authenticated" : "Guest mode", isAuth ? "success" : "info");
        }

        function updateAuthUI(isAuth) {
            const badge = document.getElementById("authStatusBadge");
            const info = document.getElementById("userInfoDisplay");
            const loginBtn = document.getElementById("loginButton");
            const logoutBtn = document.getElementById("logoutButton");
            const authReq = document.querySelectorAll(".auth-required");

            if (isAuth) {
                badge.className = "auth-badge authenticated";
                badge.innerHTML = '<span class="auth-indicator"></span><span>Authenticated</span>';
                info.style.display = "block";
                loginBtn.disabled = true;
                logoutBtn.disabled = false;
                authReq.forEach(btn => btn.disabled = false);
                ["authCheckoutWarning", "subsWarning", "ordersWarning", "transactionsWarning"].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.style.display = "none";
                });
            } else {
                badge.className = "auth-badge guest";
                badge.innerHTML = '<span class="auth-indicator"></span><span>Guest Mode</span>';
                info.style.display = "none";
                loginBtn.disabled = false;
                logoutBtn.disabled = true;
                authReq.forEach(btn => btn.disabled = true);
                ["authCheckoutWarning", "subsWarning", "ordersWarning", "transactionsWarning"].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.style.display = "block";
                });
            }
        }

        function openGuestCheckoutProduct(theme) {
            if (!window.paycan) return showMsg("SDK loading...", "error");
            window.paycan.openCheckoutModal(window.demoProductId, { theme });
        }

        function openGuestCheckoutPrice(theme) {
            if (!window.paycan) return showMsg("SDK loading...", "error");
            window.paycan.openCheckoutModalPrice(window.demoPriceId, { theme });
        }

        function openAuthCheckoutProduct(theme) {
            if (!window.paycan) return showMsg("SDK loading...", "error");
            if (!window.paycan.isAuthenticated()) return showMsg("Please login first", "error");
            window.paycan.openCheckoutModal(window.demoProductId, { theme });
        }

        function openAuthCheckoutPrice(theme) {
            if (!window.paycan) return showMsg("SDK loading...", "error");
            if (!window.paycan.isAuthenticated()) return showMsg("Please login first", "error");
            window.paycan.openCheckoutModalPrice(window.demoPriceId, { theme });
        }

        function openProductsModal(theme) {
            if (!window.paycan) return showMsg("SDK loading...", "error");
            window.paycan.openProductsModal({ theme });
        }

        function openProductsModalFiltered(type) {
            if (!window.paycan) return showMsg("SDK loading...", "error");
            window.paycan.openProductsModal({ type, theme: "light" });
        }

        function openSubscriptionsModal(theme) {
            if (!window.paycan || !window.SubscriptionsModal) return showMsg("SDK loading...", "error");
            if (!window.paycan.isAuthenticated()) return showMsg("Please login first", "error");
            const modal = new window.SubscriptionsModal(window.paycan, { theme });
            modal.open();
        }

        function openOrdersModal(theme) {
            if (!window.paycan || !window.OrdersModal) return showMsg("SDK loading...", "error");
            if (!window.paycan.isAuthenticated()) return showMsg("Please login first", "error");
            const modal = new window.OrdersModal(window.paycan, { theme });
            modal.open();
        }

        function openTransactionsModal(theme) {
            if (!window.paycan || !window.TransactionsModal) return showMsg("SDK loading...", "error");
            if (!window.paycan.isAuthenticated()) return showMsg("Please login first", "error");
            const modal = new window.TransactionsModal(window.paycan, { theme });
            modal.open();
        }

        // Make updateAuthUI globally accessible for the module script
        window.updateAuthUI = updateAuthUI;
    </script>
</x-filament-panels::page>
