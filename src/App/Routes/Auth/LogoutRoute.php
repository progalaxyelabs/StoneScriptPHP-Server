<?php

namespace App\Routes\Auth;

use ApiResponse;
use IRouteHandler;
use StoneScriptPHP\Auth\AuthService;
use StoneScriptPHP\Database;

/**
 * POST /api/auth/logout
 * Logout and invalidate refresh token
 */
class LogoutRoute implements IRouteHandler
{
    public string $refresh_token;

    public function validation_rules(): array
    {
        return [
            'refresh_token' => ['required'],
        ];
    }

    public function process(): ApiResponse
    {
        // Initialize AuthService with database connection
        $db = Database::connection();
        $auth = new AuthService($db);

        // Attempt logout
        $result = $auth->logout($this->refresh_token);

        // Check for errors
        if (isset($result['error'])) {
            return new ApiResponse('error', $result['error'], null, 400);
        }

        // Return success
        return res_ok(['success' => true], 'Logout successful');
    }
}
