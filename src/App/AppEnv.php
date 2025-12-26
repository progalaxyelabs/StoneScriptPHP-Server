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
class AppEnv extends FrameworkEnv
{
    /**
     * Separate instance storage for application Env
     * This allows AppEnv to coexist with StoneScriptPHP\Env singleton
     */
    protected static ?AppEnv $_derivedInstance = null;

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

    /**
     * Override get_instance to return AppEnv instance
     *
     * This creates a separate singleton from StoneScriptPHP\Env, allowing
     * the framework's Env (created in bootstrap.php) and the application's
     * AppEnv to coexist independently. Both read from the same .env file.
     *
     * @return static AppEnv instance (or subclass if further extended)
     */
    public static function get_instance(): static
    {
        if (!static::$_derivedInstance) {
            static::$_derivedInstance = new static();
        }
        return static::$_derivedInstance;
    }
}
