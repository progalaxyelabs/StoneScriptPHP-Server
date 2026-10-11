<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\AppEnv;

/**
 * The files a new project starts from must be correct as shipped.
 */
class ShippedConfigTest extends TestCase
{
    public function test_allowed_origins_is_a_literal_list_of_exact_origins(): void
    {
        $origins = require CONFIG_PATH . 'allowed-origins.php';

        $this->assertIsArray($origins);
        $this->assertNotSame([], $origins, 'an empty list makes the OAuth popup fail closed and CORS reject every origin');
        foreach ($origins as $origin) {
            $this->assertIsString($origin);
            $this->assertMatchesRegularExpression('#^https?://[^/*\s]+$#', $origin, 'exact origin only: scheme + host[:port], no path, no wildcard');
        }

        // Literal strings only: the runtime and the stone CLI warn and fall back to the
        // ALLOWED_ORIGINS env var when the file computes its values (getenv(), concatenation...).
        $allowed = [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_RETURN, T_CONSTANT_ENCAPSED_STRING];
        foreach (token_get_all((string) file_get_contents(CONFIG_PATH . 'allowed-origins.php')) as $token) {
            if (is_array($token)) {
                $this->assertContains($token[0], $allowed, 'allowed-origins.php must be a plain return of string literals, found ' . token_name($token[0]));
            } else {
                $this->assertContains($token, ['[', ']', ',', ';'], "unexpected '$token' in allowed-origins.php");
            }
        }
    }

    public function test_auth_config_resolves_environment_instead_of_pinning_values(): void
    {
        $auth = require CONFIG_PATH . 'auth.php';

        // null = read from .env, so the file and .env cannot disagree.
        $this->assertNull($auth['mode']);
        $this->assertNull($auth['server']['url']);
        $this->assertNull($auth['server']['issuer']);
        $this->assertNull($auth['platform']['code']);
        // A new project has no pre-/api/auth clients; the legacy prefix only logs a deprecation.
        // It sits under 'features' because only that list is forwarded to the auth routes.
        $this->assertFalse($auth['features']['legacy_compat']);
        $this->assertTrue($auth['features']['exchange']);
    }

    public function test_subscription_module_is_off_so_no_project_goes_read_only_by_default(): void
    {
        $config = require CONFIG_PATH . 'subscription.php';

        $this->assertFalse($config['enabled']);

        // The framework's own gate (a config without 'enabled' counts as ON).
        $gate = new \ReflectionMethod(\StoneScriptPHP\Application::class, 'isSubscriptionEnabled');
        $this->assertFalse($gate->invoke(null, $config), 'SubscriptionMiddleware must not be wired by default');
    }

    public function test_every_active_env_example_key_is_read_by_the_framework(): void
    {
        // App\AppEnv is the application's own typed extension of the framework Env.
        $known = array_map(static fn(\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(AppEnv::class))->getProperties(\ReflectionProperty::IS_PUBLIC));
        // Read through Env::secret() rather than a typed property.
        $known[] = 'TRUSTED_PROXIES';

        $stale = [];
        foreach (file(__DIR__ . '/../../.env.example', FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $m) === 1 && !in_array($m[1], $known, true)) {
                $stale[] = $m[1];
            }
        }

        $this->assertSame([], $stale, 'keys in .env.example that the framework never reads (a stale key silently does nothing)');
    }

    public function test_env_example_documents_the_always_on_framework_settings(): void
    {
        $env = (string) file_get_contents(__DIR__ . '/../../.env.example');

        foreach (['TRUSTED_PROXIES', 'PERSISTENCE_CONTRACT', 'DB_HYDRATION_MODE', 'HEAD_EXECUTES_GET', 'REFRESH_TOKEN_STORE', 'JWT_ISSUER', 'ALLOWED_ORIGINS'] as $key) {
            $this->assertMatchesRegularExpression('/^#?\s*' . $key . '=/m', $env, "$key must be documented in .env.example");
        }
    }

    public function test_env_example_ships_no_active_override_of_origins_or_cookie_security(): void
    {
        $lines = file(__DIR__ . '/../../.env.example', FILE_IGNORE_NEW_LINES);

        // An ALLOWED_ORIGINS variable overrides src/config/allowed-origins.php; an empty
        // AUTH_COOKIE_SECURE is read as false and turns the Secure flag off.
        foreach (['ALLOWED_ORIGINS', 'AUTH_COOKIE_SECURE', 'AUTH_COOKIE_DOMAIN'] as $key) {
            $this->assertSame([], preg_grep('/^' . $key . '=/', $lines), "$key must be commented out in .env.example");
            $this->assertNotSame([], preg_grep('/^#\s*' . $key . '=/', $lines), "$key must stay documented (commented) in .env.example");
        }
    }

    public function test_entry_point_runs_the_shared_application_config_with_typed_auth(): void
    {
        $index = (string) file_get_contents(__DIR__ . '/../../public/index.php');
        $this->assertStringContainsString("Application::run(require CONFIG_PATH . 'application.php')", $index, 'public/index.php must run src/config/application.php, the config the auth smoke test boots');

        $config = require CONFIG_PATH . 'application.php';
        $this->assertTrue($config['typed_auth'], 'without typed_auth the framework does not wire the access-token middleware');
    }
}
