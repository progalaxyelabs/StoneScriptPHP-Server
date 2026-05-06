<?php

/**
 * JWT Excluded Paths
 *
 * Paths listed here bypass JWT authentication.
 * Keep this list minimal - only truly public endpoints should be here.
 *
 * NOTE: /auth/* routes registered by ExternalAuthRoutes are auto-excluded.
 * NOTE: /subscription/* public paths are auto-merged by Application::run().
 *
 * @see https://stonescriptphp.org/docs/auth
 */
return [
    // Health check (always public)
    '/',
    '/health',
];
