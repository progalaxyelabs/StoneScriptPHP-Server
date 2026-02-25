<?php

define('INDEX_START_TIME', microtime(true));
define('ROOT_PATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('SRC_PATH', ROOT_PATH . 'src' . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', SRC_PATH . 'config' . DIRECTORY_SEPARATOR);

if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', ($_SERVER['DEBUG_MODE'] ?? 'false') === 'true');
}

require_once ROOT_PATH . 'vendor/autoload.php';

use StoneScriptPHP\Application;

Application::run([
    'routes' => require CONFIG_PATH . 'routes.php',
    'auth'   => require CONFIG_PATH . 'auth.php',
]);
