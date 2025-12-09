<?php

namespace App;

use Framework\Env as FrameworkEnv;

/**
 * Application Environment Configuration
 *
 * Extends Framework\Env to add application-specific environment variables.
 * The framework handles core variables (DEBUG_MODE, TIMEZONE, DATABASE_*, etc.)
 * Add your application-specific variables here.
 */
class Env extends FrameworkEnv
{
    // Application-specific environment variables
    public $APP_NAME;
    public $APP_ENV;
    public $APP_PORT;

    // Google OAuth (optional)
    public $GOOGLE_CLIENT_ID;

    // JWT Configuration
    public $JWT_PRIVATE_KEY_PATH;
    public $JWT_PUBLIC_KEY_PATH;
    public $JWT_EXPIRY;

    // CORS Configuration
    public $ALLOWED_ORIGINS;

    /**
     * Override getSchema to merge application-specific variables with framework variables
     */
    public static function getSchema(): array
    {
        // Get parent schema (framework variables)
        $parentSchema = parent::getSchema();

        // Add application-specific variables
        $appSchema = [
            'APP_NAME' => [
                'type' => 'string',
                'required' => false,
                'default' => 'My API',
                'description' => 'Application name'
            ],
            'APP_ENV' => [
                'type' => 'string',
                'required' => false,
                'default' => 'development',
                'description' => 'Application environment (development, staging, production)'
            ],
            'APP_PORT' => [
                'type' => 'int',
                'required' => false,
                'default' => 9100,
                'description' => 'Default port for development server'
            ],
            'GOOGLE_CLIENT_ID' => [
                'type' => 'string',
                'required' => false,
                'default' => null,
                'description' => 'Google OAuth client ID'
            ],
            'JWT_PRIVATE_KEY_PATH' => [
                'type' => 'string',
                'required' => false,
                'default' => './keys/jwt-private.pem',
                'description' => 'Path to JWT private key'
            ],
            'JWT_PUBLIC_KEY_PATH' => [
                'type' => 'string',
                'required' => false,
                'default' => './keys/jwt-public.pem',
                'description' => 'Path to JWT public key'
            ],
            'JWT_EXPIRY' => [
                'type' => 'int',
                'required' => false,
                'default' => 3600,
                'description' => 'JWT token expiry time in seconds'
            ],
            'ALLOWED_ORIGINS' => [
                'type' => 'string',
                'required' => false,
                'default' => 'http://localhost:3000,http://localhost:4200',
                'description' => 'Comma-separated list of allowed CORS origins'
            ],
        ];

        // Merge and return
        return array_merge($parentSchema, $appSchema);
    }
}
