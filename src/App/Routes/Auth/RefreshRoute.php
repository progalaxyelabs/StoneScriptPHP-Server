<?php

namespace App\Routes\Auth;

use ApiResponse;
use IRouteHandler;
use StoneScriptPHP\Auth\AuthService;
use StoneScriptPHP\Database;

/**
 * POST /api/auth/refresh
 * Refresh access token using refresh token
 */
class RefreshRoute implements IRouteHandler
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

        // Attempt token refresh
        $result = $auth->refresh($this->refresh_token);

        // Check for errors
        if (isset($result['error'])) {
            return new ApiResponse('error', $result['error'], null, 401);
        }

        // Return new access token
        return res_ok([
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'],
        ], 'Token refreshed successfully');
    }
}
