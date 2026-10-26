<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StoneScriptPHP\ApiResponse;
use StoneScriptPHP\Auth\Middleware\AuthMiddlewareRegistrar;
use StoneScriptPHP\Auth\RsaJwtHandler;
use StoneScriptPHP\Auth\TokenExchangeService;
use StoneScriptPHP\Env;

/**
 * Scaffold-level smoke: with the environment the skeleton ships (tests/bootstrap.php sets a
 * throwaway one), a token the real flow mints reaches a protected route in both auth modes.
 * The detailed matrix (wrong issuer, forged signature, expiry, purpose...) lives in the
 * framework's own TypedAuthForModeRoundTripTest.
 */
class AuthSmokeTest extends TestCase
{
    private string $savedAuthorization = '';

    protected function setUp(): void
    {
        $this->savedAuthorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        if ($this->savedAuthorization !== '') {
            $_SERVER['HTTP_AUTHORIZATION'] = $this->savedAuthorization;
        }
    }

    private function reachesProtectedRoute(string $mode, string $token): ApiResponse
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        $middleware = AuthMiddlewareRegistrar::forMode($mode, Env::get_instance());
        $this->assertNotSame([], $middleware);

        return $middleware[0]->handle(
            ['route' => ['access' => 'authorization', 'token_type' => 'access', 'is_public' => false]],
            static fn(array $r): ApiResponse => new ApiResponse('ok', 'handler reached')
        );
    }

    public function test_builtin_login_token_reaches_a_protected_route(): void
    {
        $token = (new RsaJwtHandler())->generateToken(['user_id' => 'u1', 'sub' => 'u1']);

        $this->assertSame('ok', $this->reachesProtectedRoute('builtin', $token)->status);
    }

    public function test_external_api_token_reaches_a_protected_route(): void
    {
        $env = Env::get_instance();
        $token = (new TokenExchangeService())->exchangeApiToken(
            ['sub' => 'u1', 'identity_id' => 'u1', 'tenant_id' => 't1'],
            'owner',
            ['private_key_path' => $env->JWT_PRIVATE_KEY_PATH, 'issuer' => $env->JWT_ISSUER, 'ttl' => 600]
        );

        $this->assertSame('ok', $this->reachesProtectedRoute('external', $token)->status);
        $this->assertSame('ok', $this->reachesProtectedRoute('hybrid', $token)->status);
    }

    public function test_a_garbage_token_is_refused(): void
    {
        $r = $this->reachesProtectedRoute('builtin', 'not.a.jwt');

        $this->assertSame('error', $r->status);
        $this->assertSame(401, $r->httpStatusCode);
    }

    /**
     * Boots the real Application::run() in a child process (it keeps static state and writes its
     * request log to STDERR) with the config public/index.php passes (src/config/application.php), for one
     * AUTH_MODE, plus one protected route, and issues one request.
     *
     * @return array{status:int, body:string}
     */
    private function requestThroughApplication(string $mode, string $uri, ?string $token = null): array
    {
        $env = getenv();
        $env['AUTH_MODE'] = $mode;
        $cmd = [PHP_BINARY, __DIR__ . '/ApplicationProbe.php', $uri, $token ?? ''];
        // stderr goes to a file: reading two pipes one after the other can deadlock when the child
        // fills the one we are not reading.
        $errFile = tempnam(sys_get_temp_dir(), 'probe-err-');
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']], $pipes, dirname(__DIR__, 2), $env);
        $this->assertIsResource($proc, 'could not start the probe process');
        $out = (string) stream_get_contents($pipes[1]);
        proc_close($proc);
        $err = (string) file_get_contents($errFile);
        @unlink($errFile);

        // Since framework 13.1 a rejected token is logged at warning level, and in the CLI the logger
        // writes to stdout: the probe's result is its LAST stdout line, not the whole stream.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $out)), 'strlen'));
        $decoded = json_decode((string) end($lines), true);
        $this->assertIsArray($decoded, "probe produced no result in $mode mode. stdout: $out stderr: $err");

        return $decoded;
    }

    /** @return array<string, array{string}> */
    public static function authModes(): array
    {
        return ['builtin' => ['builtin'], 'external' => ['external'], 'hybrid' => ['hybrid']];
    }

    #[DataProvider('authModes')]
    public function test_shipped_app_boots_and_serves_health_in_every_auth_mode(string $mode): void
    {
        $r = $this->requestThroughApplication($mode, '/health');

        $this->assertSame(200, $r['status'], "boot failed in $mode mode: {$r['body']}");
        $this->assertStringNotContainsString('env_configuration_invalid', $r['body']);
        $this->assertStringNotContainsString('auth_wiring_required', $r['body']);
    }

    #[DataProvider('authModes')]
    public function test_protected_route_refuses_no_token_and_accepts_a_real_token_in_every_auth_mode(string $mode): void
    {
        $this->assertSame(401, $this->requestThroughApplication($mode, '/probe-protected')['status'], "$mode: unauthenticated request was not refused");

        $env = Env::get_instance();
        $token = $mode === 'builtin'
            ? (new RsaJwtHandler())->generateToken(['user_id' => 'u1', 'sub' => 'u1'])
            : (new TokenExchangeService())->exchangeApiToken(
                ['sub' => 'u1', 'identity_id' => 'u1'],
                'owner',
                ['private_key_path' => $env->JWT_PRIVATE_KEY_PATH, 'issuer' => $env->JWT_ISSUER, 'ttl' => 600]
            );
        $r = $this->requestThroughApplication($mode, '/probe-protected', $token);

        $this->assertSame(200, $r['status'], "$mode: a validly signed token did not reach the route: {$r['body']}");
    }

    /**
     * A token signed by a key this API does not trust, carrying valid-looking claims.
     */
    private function tokenSignedByAThrowawayKey(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $now = time();

        return \Firebase\JWT\JWT::encode(
            ['sub' => 'u1', 'user_id' => 'u1', 'identity_id' => 'u1', 'iss' => Env::get_instance()->JWT_ISSUER, 'iat' => $now, 'exp' => $now + 600],
            $pem,
            'RS256'
        );
    }

    #[DataProvider('authModes')]
    public function test_a_token_signed_by_another_key_is_refused_in_every_auth_mode(string $mode): void
    {
        $r = $this->requestThroughApplication($mode, '/probe-protected', $this->tokenSignedByAThrowawayKey());

        $this->assertSame(401, $r['status'], "$mode: a forged token was not refused: {$r['body']}");
    }

    #[DataProvider('authModes')]
    public function test_a_token_that_is_not_a_jwt_is_refused_in_every_auth_mode(string $mode): void
    {
        $r = $this->requestThroughApplication($mode, '/probe-protected', 'not.a.jwt');

        $this->assertSame(401, $r['status'], "$mode: a malformed token was not refused: {$r['body']}");
    }
}
