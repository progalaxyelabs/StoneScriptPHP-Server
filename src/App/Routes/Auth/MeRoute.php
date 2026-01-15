<?php

namespace App\Routes\Auth;

use ApiResponse;
use IRouteHandler;
use StoneScriptPHP\Auth\AuthService;
use StoneScriptPHP\Database;

/**
 * GET /api/auth/me
 * Get current authenticated user
 */
class MeRoute implements IRouteHandler
{
    public function validation_rules(): array
    {
        return [];
    }

    public function process(): ApiResponse
    {
        // Initialize AuthService with database connection
        $db = Database::connection();
        $auth = new AuthService($db);

        // Get current user
        $user = $auth->getCurrentUser();

        // Check if user is authenticated
        if (!$user) {
            return new ApiResponse('error', 'Not authenticated', null, 401);
        }

        // Return user info
        return res_ok($user, 'User retrieved successfully');
    }
}
