<?php

// Child-process helper for AuthSmokeTest (not a test class).
//
// Runs the real Application::run() with the config public/index.php passes
// (src/config/application.php), plus one protected route, and prints {"status":<http code>,"body":"<response body>"} as the only stdout line.
// Usage: php ApplicationProbe.php <uri> [<bearer token>]   (AUTH_MODE and the rest from the environment)

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('SRC_PATH', ROOT_PATH . 'src' . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', SRC_PATH . 'config' . DIRECTORY_SEPARATOR);
define('DEBUG_MODE', false);

require ROOT_PATH . 'vendor/autoload.php';

use StoneScriptPHP\ApiResponse;
use StoneScriptPHP\Application;
use StoneScriptPHP\IRouteHandler;

// An anonymous class: a named class in this PSR-4 folder would not match its file name.
$protectedRoute = new class implements IRouteHandler {
    public function validation_rules(): array
    {
        return [];
    }

    public function process(): ApiResponse
    {
        return new ApiResponse('ok', 'protected handler reached');
    }
};

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $argv[1] ?? '/';
if (($argv[2] ?? '') !== '') {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $argv[2];
}

// The same configuration public/index.php runs (src/config/application.php), plus one protected route.
$config = require CONFIG_PATH . 'application.php';
$config['routes']['GET']['/probe-protected'] = ['handler' => $protectedRoute::class, 'service' => 'shared', 'group' => 'probe', 'is_public' => false];

ob_start();
Application::run($config);
$body = (string) ob_get_clean();

echo json_encode(['status' => http_response_code() ?: 200, 'body' => $body]), "\n";
