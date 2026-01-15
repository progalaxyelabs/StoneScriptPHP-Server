<?php

namespace App\Routes\Auth;

use ApiResponse;
use IRouteHandler;
use StoneScriptPHP\Auth\AuthService;
use StoneScriptPHP\Database;

/**
 * POST /api/auth/register
 * Register new user
 */
class RegisterRoute implements IRouteHandler
{
    public string $email;
    public string $password;
    public ?string $name = null;

    public function validation_rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6'],
            'name' => ['optional'],
        ];
    }

    public function process(): ApiResponse
    {
        // Initialize AuthService with database connection
        $db = Database::connection();
        $auth = new AuthService($db);

        // Attempt registration
        $result = $auth->register($this->email, $this->password, $this->name);

        // Check for errors
        if (isset($result['error'])) {
            return new ApiResponse('error', $result['error'], null, 400);
        }

        // Return tokens
        return res_ok([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in' => $result['expires_in'],
        ], 'Registration successful');
    }
}
