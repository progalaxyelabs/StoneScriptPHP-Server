<?php

declare(strict_types=1);

namespace App\Routes\Auth;

use StoneScriptPHP\ApiResponse;
use StoneScriptPHP\IRouteHandler;
use StoneScriptPHP\Database;
use StoneScriptPHP\Auth\MultiAuthJwtValidator;
use App\Lib\PlatformJwtSigner;

/**
 * Token Exchange Route
 *
 * POST /api/auth/exchange
 *
 * Exchanges an identity token (from auth service) for a platform token (with roles).
 *
 * Flow:
 * 1. Extract identity JWT from Authorization header
 * 2. Validate against auth service JWKS
 * 3. Look up user roles from tenant DB (platform-specific)
 * 4. Sign new platform JWT with roles included
 * 5. Return platform token
 *
 * This is a SKELETON implementation. Platforms must customize:
 * - Role lookup query for their tenant DB schema
 * - Platform JWT signing key configuration
 * - Additional claims as needed (permissions, features, etc.)
 */
class TokenExchangeRoute implements IRouteHandler
{
    private const ACCESS_TOKEN_TTL = 3600;  // 1 hour

    public function validation_rules(): array
    {
        return [];
    }

    public function process(): ApiResponse
    {
        // ─────────────────────────────────────────────────────────────────────────
        // Step 1: Extract identity token from Authorization header
        // ─────────────────────────────────────────────────────────────────────────

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return res_error('Authorization header with Bearer token required');
        }
        $identityToken = $matches[1];

        // ─────────────────────────────────────────────────────────────────────────
        // Step 2: Validate identity token against auth service JWKS
        // ─────────────────────────────────────────────────────────────────────────

        $authConfig = require __DIR__ . '/../../../config/auth.php';
        $authServerUrl = $authConfig['server']['url'] ?? 'http://localhost:3139';
        $authIssuer = $authConfig['server']['issuer'] ?: $authServerUrl;
        $jwksPath = $authConfig['server']['paths']['jwks'] ?? '/api/auth/jwks';

        $validator = new MultiAuthJwtValidator([
            'auth' => [
                'issuer'   => $authIssuer,
                'jwks_url' => $authServerUrl . $jwksPath,
                'audience' => null,  // Auth service may not set audience
            ],
        ]);

        $claims = $validator->validateJWT($identityToken);
        if (!$claims) {
            return res_error('Invalid or expired identity token', null, 401);
        }

        // Extract identity info from validated token
        $identityId = $claims['identity_id'] ?? $claims['sub'] ?? null;
        $tenantId = $claims['tenant_id'] ?? null;
        $tenantSlug = $claims['tenant_slug'] ?? null;
        $platformCode = $claims['platform_code'] ?? null;

        if (!$identityId) {
            return res_error('Token missing identity_id claim');
        }

        if (!$tenantId) {
            return res_error('Token missing tenant_id claim - user must select a tenant first');
        }

        // ─────────────────────────────────────────────────────────────────────────
        // Step 3: Look up user roles from tenant DB
        // ─────────────────────────────────────────────────────────────────────────
        //
        // PLATFORM CUSTOMIZATION POINT:
        // This query should match your tenant DB schema. Common patterns:
        //
        // Option A: user_roles junction table
        //   SELECT r.name FROM user_roles ur
        //   JOIN roles r ON ur.role_id = r.id
        //   WHERE ur.user_identity_id = :identity_id
        //
        // Option B: roles array column on users table
        //   SELECT roles FROM users WHERE identity_id = :identity_id
        //
        // Option C: Single role column
        //   SELECT role FROM users WHERE identity_id = :identity_id

        try {
            // Example: Single role from users table (simplest pattern)
            // Replace this with your platform's actual role lookup
            $roleResult = Database::fn('get_user_role', [$identityId]);
            $roles = [];
            $activeRole = 'member';  // Default role

            if (!empty($roleResult) && isset($roleResult[0])) {
                // Adjust based on your function's return format
                $roles = $roleResult[0]['roles'] ?? [$roleResult[0]['role'] ?? 'member'];
                $activeRole = is_array($roles) ? ($roles[0] ?? 'member') : $roles;
                if (is_string($roles)) {
                    $roles = [$roles];
                }
            }
        } catch (\Exception $e) {
            // If role lookup fails, log and return error
            error_log("Token exchange role lookup failed: " . $e->getMessage());
            return res_error('Failed to retrieve user roles');
        }

        // ─────────────────────────────────────────────────────────────────────────
        // Step 4: Build platform token claims
        // ─────────────────────────────────────────────────────────────────────────
        //
        // PLATFORM CUSTOMIZATION POINT:
        // Add any additional claims your platform needs:
        // - permissions: ['read:orders', 'write:orders']
        // - features: ['advanced_reports', 'bulk_export']
        // - subscription_tier: 'premium'

        $now = time();
        $platformClaims = [
            // Identity (carried over from auth token)
            'identity_id'  => $identityId,
            'tenant_id'    => $tenantId,
            'tenant_slug'  => $tenantSlug,
            'platform_code' => $platformCode,

            // Roles (from tenant DB)
            'role'  => $activeRole,
            'roles' => $roles,

            // Standard JWT claims
            'iat' => $now,
            'exp' => $now + self::ACCESS_TOKEN_TTL,
            'iss' => $_SERVER['HTTP_HOST'] ?? 'platform-api',
            'sub' => $identityId,

            // Token type marker (helps distinguish from identity tokens)
            'token_type' => 'platform',
        ];

        // ─────────────────────────────────────────────────────────────────────────
        // Step 5: Sign platform JWT
        // ─────────────────────────────────────────────────────────────────────────
        //
        // PLATFORM CUSTOMIZATION POINT:
        // Configure your signing key in config/auth.php or environment:
        //   'platform_jwt' => [
        //       'private_key' => file_get_contents('/path/to/private.pem'),
        //       'algorithm' => 'RS256',
        //   ]

        try {
            $signer = new PlatformJwtSigner();
            $platformToken = $signer->sign($platformClaims);
        } catch (\Exception $e) {
            error_log("Platform JWT signing failed: " . $e->getMessage());
            return res_error('Failed to issue platform token');
        }

        // ─────────────────────────────────────────────────────────────────────────
        // Step 6: Return platform token
        // ─────────────────────────────────────────────────────────────────────────

        return res_ok([
            'access_token' => $platformToken,
            'token_type'   => 'Bearer',
            'expires_in'   => self::ACCESS_TOKEN_TTL,
            'role'         => $activeRole,
            'roles'        => $roles,
        ], 'Token exchanged successfully');
    }
}
