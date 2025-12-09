<?php

namespace App;

/**
 * Application Environment Configuration
 *
 * This file defines static environment variables loaded from .env file
 * Update these values in your .env file, NOT here!
 */
class Env
{
    // Database Configuration
    public static string $DATABASE_HOST = '';
    public static int    $DATABASE_PORT = 5432;
    public static string $DATABASE_USER = '';
    public static string $DATABASE_PASSWORD = '';
    public static string $DATABASE_DBNAME = '';
    public static int    $DATABASE_TIMEOUT = 30;
    public static string $DATABASE_APPNAME = 'StoneScriptPHP';

    // Email Configuration (ZeptoMail)
    public static string $ZEPTOMAIL_BOUNCE_ADDRESS = '';
    public static string $ZEPTOMAIL_SENDER_EMAIL = '';
    public static string $ZEPTOMAIL_SENDER_NAME = '';
    public static string $ZEPTOMAIL_SEND_MAIL_TOKEN = '';

    // Google OAuth
    public static string $GOOGLE_CLIENT_ID = '';

    // Application Settings
    public static int    $DEBUG_MODE = 0;
    public static string $TIMEZONE = 'UTC';
}

