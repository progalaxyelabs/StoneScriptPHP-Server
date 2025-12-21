<?php

namespace App;

use StoneScriptPHP\Env as FrameworkEnv;

/**
 * Application Environment Configuration
 *
 * Extends StoneScriptPHP\Env to add application-specific environment variables.
 * The framework handles core variables (DEBUG_MODE, TIMEZONE, DATABASE_*, etc.)
 * Add your application-specific typed properties here.
 */
class Env extends FrameworkEnv
{
    // Application-specific environment variables with types and defaults
    // Note: Parent class already has APP_NAME, APP_ENV, APP_PORT, JWT_*, and ALLOWED_ORIGINS
    // Only add truly application-specific variables here

    // Google OAuth (optional)
    public ?string $GOOGLE_CLIENT_ID = null;
    public ?string $GOOGLE_CLIENT_SECRET = null;

    // Add more application-specific variables as needed
    // Example:
    // public ?string $STRIPE_API_KEY = null;
    // public string $CUSTOM_FEATURE_FLAG = 'enabled';
}
