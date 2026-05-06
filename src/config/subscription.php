<?php

/**
 * Subscription Module Configuration
 *
 * Configures the StoneScriptPHP SubscriptionRoutes and SubscriptionMiddleware.
 * Routes are registered at {prefix}/... by Application::run().
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
