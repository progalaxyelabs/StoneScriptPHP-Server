<?php

declare(strict_types=1);

namespace App\Lib;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Platform JWT Signer
 *
 * Signs platform tokens for the token exchange flow.
 *
 * PLATFORM CUSTOMIZATION REQUIRED:
 * 1. Generate RSA key pair:
 *    openssl genrsa -out keys/platform-private.pem 2048
 *    openssl rsa -in keys/platform-private.pem -pubout -out keys/platform-public.pem
 *
 * 2. Configure in .env or config/auth.php:
 *    PLATFORM_JWT_PRIVATE_KEY=/path/to/private.pem
 *    PLATFORM_JWT_PUBLIC_KEY=/path/to/public.pem
 *    PLATFORM_JWT_ALGORITHM=RS256
 *
 * 3. Expose public key via JWKS endpoint if other services need to validate:
 *    GET /api/auth/platform-jwks
 */
class PlatformJwtSigner
{
    private string $privateKey;
    private string $publicKey;
    private string $algorithm;
    private string $keyId;

    public function __construct()
    {
        // Load configuration from auth.php or environment
        $authConfig = require __DIR__ . '/../../config/auth.php';
        $platformJwtConfig = $authConfig['platform_jwt'] ?? [];

        // Private key for signing
        $privateKeyPath = $platformJwtConfig['private_key_path']
            ?? $_ENV['PLATFORM_JWT_PRIVATE_KEY']
            ?? __DIR__ . '/../../../keys/platform-private.pem';

        // Public key for verification (if needed)
        $publicKeyPath = $platformJwtConfig['public_key_path']
            ?? $_ENV['PLATFORM_JWT_PUBLIC_KEY']
            ?? __DIR__ . '/../../../keys/platform-public.pem';

        $this->algorithm = $platformJwtConfig['algorithm']
            ?? $_ENV['PLATFORM_JWT_ALGORITHM']
            ?? 'RS256';

        // Generate key ID from public key hash (for JWKS)
        $this->keyId = $platformJwtConfig['key_id']
            ?? $_ENV['PLATFORM_JWT_KEY_ID']
            ?? 'platform-key-1';

        // Load keys
        if (file_exists($privateKeyPath)) {
            $this->privateKey = file_get_contents($privateKeyPath);
        } else {
            throw new \RuntimeException(
                "Platform JWT private key not found at: {$privateKeyPath}. " .
                "Generate with: openssl genrsa -out keys/platform-private.pem 2048"
            );
        }

        if (file_exists($publicKeyPath)) {
            $this->publicKey = file_get_contents($publicKeyPath);
        } else {
            // Public key is optional for signing, required for JWKS
            $this->publicKey = '';
        }
    }

    /**
     * Sign claims into a JWT token.
     *
     * @param array $claims Token payload (must include 'exp', 'iat', 'sub')
     * @return string Signed JWT
     */
    public function sign(array $claims): string
    {
        // Add key ID to header for JWKS matching
        $headers = ['kid' => $this->keyId];

        return JWT::encode($claims, $this->privateKey, $this->algorithm, null, $headers);
    }

    /**
     * Verify and decode a platform token.
     *
     * @param string $token JWT to verify
     * @return array Decoded claims
     * @throws \Exception If token is invalid
     */
    public function verify(string $token): array
    {
        if (empty($this->publicKey)) {
            throw new \RuntimeException('Public key not configured for token verification');
        }

        $decoded = JWT::decode($token, new Key($this->publicKey, $this->algorithm));
        return (array) $decoded;
    }

    /**
     * Get JWKS representation of the public key.
     *
     * Use this to expose a /api/auth/platform-jwks endpoint so other
     * services can validate platform tokens.
     *
     * @return array JWKS structure
     */
    public function getJWKS(): array
    {
        if (empty($this->publicKey)) {
            return ['keys' => []];
        }

        // Parse public key
        $keyResource = openssl_pkey_get_public($this->publicKey);
        if (!$keyResource) {
            throw new \RuntimeException('Failed to parse public key');
        }

        $keyDetails = openssl_pkey_get_details($keyResource);
        if ($keyDetails['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new \RuntimeException('Only RSA keys are supported for JWKS');
        }

        // Extract RSA components
        $n = rtrim(strtr(base64_encode($keyDetails['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($keyDetails['rsa']['e']), '+/', '-_'), '=');

        return [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => $this->algorithm,
                    'kid' => $this->keyId,
                    'n'   => $n,
                    'e'   => $e,
                ],
            ],
        ];
    }

    /**
     * Get the key ID used in JWT headers.
     */
    public function getKeyId(): string
    {
        return $this->keyId;
    }
}
