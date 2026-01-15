<?php

namespace App\Routes\Auth;

use ApiResponse;
use IRouteHandler;
use StoneScriptPHP\Auth\AuthService;
use StoneScriptPHP\Database;

/**
 * POST /api/auth/login
 * Login with email and password
 */
class LoginRoute implements IRouteHandler
{
    public string $email;
    public string $password;

    public function validation_rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6'],
        ];
    }

    public function process(): ApiResponse
    {
        // Initialize AuthService with database connection
        $db = Database::connection();
        $auth = new AuthService($db);

        // Attempt login
        $result = $auth->login($this->email, $this->password);

        // Check for errors
        if (isset($result['error'])) {
            return new ApiResponse('error', $result['error'], null, 401);
        }

        // Return tokens
        return res_ok([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
        ], 'Login successful');
    }
}
