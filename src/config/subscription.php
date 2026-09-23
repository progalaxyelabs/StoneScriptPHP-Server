<?php

/**
 * Subscription Module Configuration
 *
 * Configures the StoneScriptPHP SubscriptionRoutes and SubscriptionMiddleware.
 * Routes are registered at {prefix}/... by Application::run().
 *
 * NOTE: this module is considered legacy — new billing/collection work
 * should target the framework's Billing\CollectionOrchestrator seam instead
 * (bridge progalaxyelabs/stonescriptphp-payments into
 * StoneScriptPHP\Billing\Contracts\PaymentProvider — see that package's
 * README and src/Billing/README.md in the framework). This module is still
 * fully supported and untouched by the 6.x -> 9.x upgrade; left disabled by
 * default (`razorpay_webhook` and `admin_activate` are false) so it has no
 * effect until you opt in. If you enable `razorpay_webhook`, framework
 * v9.17.1+ requires you to also pass a `payment_provider` into
 * SubscriptionRoutes' config (via this array) — it now fails loud instead of
 * hard-`new`ing a payment driver directly.
 *
 * @see https://stonescriptphp.org/docs/subscription
 */

return [
    'prefix' => '/subscription',

    // Routes to enable
    'status'           => true,
    'plans'            => true,
    'razorpay_webhook' => false,  // Enable when Razorpay integration is configured
    'admin_activate'   => false,  // Enable for admin-triggered subscription activation

    // Secrets (load from environment)
    'razorpay_webhook_secret' => $_SERVER['RAZORPAY_WEBHOOK_SECRET'] ?? null,
    'admin_api_key'           => $_SERVER['ADMIN_API_KEY'] ?? null,
];
