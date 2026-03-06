<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

/**
 * Encrypts error data using a public key for secure remote debugging.
 *
 * Uses hybrid encryption: AES-256-GCM for data, RSA for key transport.
 * Only the holder of the private key can decrypt.
 */
class HadesEncrypt
{
    /**
     * Default RSA public key for HADES encryption.
     * This is a PUBLIC key - safe to commit. Only rotate if private key is compromised.
     */
    private const DEFAULT_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAkhDS4aU4JC+LWihXvssw
nOrQIGUYsoq57dliHEKy30GK54dvjhmQ9mhjOC/tQCArl2Ju/Fbl6E8dd+4di3fq
Utnixw4J/jyFlh1EevKdmmSf+Ek02OrxprntAX2auGN+SbJ/bdISS0KWEuDuNFBb
AGtWlIed0sv8CsAxGAdZIfIgvZgIckV6gLAFOnGnI/2tYxDhxALe5HGAMl8ON+lO
SE1hBHBsiamojTp3MKogMbY3Olpqwzu+5gsD3lJE9ZhzG9onsxjadYYP1bdOdxPP
rQFhScE5hvDYWQZk0UQXOzaaOw56ANZ4MsUfGlG85uFhkVniHyGevDH/V728WHcV
0Lu2pTkD2Z1jwy+ZCbXMU2wUXU0uqo+79Yf9Ne+CbRmI3R657/totxA1xMH9Wx0k
3LXkHqvi2Hv65yC0Fdp/LBGDV4KZ1f0wb/MSAY0zUUB4sWZVb7DpEXuEquP7f6Re
hsXVN7qX5xIa1ZJMvrrPgXmmLonXOrldTiHvpakSLB/3AgMBAAE=
-----END PUBLIC KEY-----
PEM;

    /**
     * Encrypt exception data for embedding in error pages.
     *
     * @param  \Throwable  $e  The exception to encrypt
     * @return string|null Base64-encoded encrypted payload, or null if encryption fails
     */
    public static function encrypt(\Throwable $e): ?string
    {
        // Use env var override if set, otherwise use hardcoded default
        $keyData = $_ENV['HADES_PUBLIC_KEY']
            ?? $_SERVER['HADES_PUBLIC_KEY']
            ?? getenv('HADES_PUBLIC_KEY')
            ?: null;

        // Convert to PEM format (handles base64-encoded keys from env vars)
        $publicKeyPem = $keyData ? self::toPem($keyData) : self::DEFAULT_PUBLIC_KEY;

        try {
            $publicKey = openssl_pkey_get_public($publicKeyPem);
            if (! $publicKey) {
                // Log for debugging - will show in container logs
                error_log('[Hades] Failed to parse public key: '.openssl_error_string());

                return null;
            }

            // Build payload - use safe methods in case app isn't fully bootstrapped
            $payload = json_encode([
                'time' => date('c'),
                'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'previous' => $e->getPrevious() ? [
                    'class' => get_class($e->getPrevious()),
                    'message' => $e->getPrevious()->getMessage(),
                ] : null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Generate random AES key and IV
            $aesKey = random_bytes(32);
            $iv = random_bytes(12); // GCM uses 12-byte IV
            $tag = '';

            // Encrypt payload with AES-256-GCM
            $encrypted = openssl_encrypt(
                $payload,
                'aes-256-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16
            );

            if ($encrypted === false) {
                error_log('[Hades] AES encryption failed: '.openssl_error_string());

                return null;
            }

            // Encrypt AES key with RSA public key
            $encryptedKey = '';
            if (! openssl_public_encrypt($aesKey, $encryptedKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
                error_log('[Hades] RSA encryption failed: '.openssl_error_string());

                return null;
            }

            // Pack: key_length (2 bytes) + encrypted_key + iv (12) + tag (16) + ciphertext
            $packed = pack('n', strlen($encryptedKey)).$encryptedKey.$iv.$tag.$encrypted;

            return base64_encode($packed);

        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Format encrypted data as HTML comment.
     */
    public static function toHtmlComment(\Throwable $e): string
    {
        $encrypted = self::encrypt($e);

        if ($encrypted === null) {
            return '';
        }

        // Friendly explanation for curious visitors
        $explanation = <<<'TEXT'

<!--
  This is an encrypted error reference for our support team.
  If you're contacting us about this issue, click "Error Reference" above to copy it.

  Named after Hades, the Greek god of the underworld (and apparently software bugs too).
  He keeps the errors down below so we can bring them back up when needed.

  HADES:
TEXT;

        return "{$explanation}{$encrypted} -->\n";
    }

    /**
     * Convert key data to PEM format.
     *
     * Accepts:
     * - Base64-encoded PEM (recommended for env vars - single line, no special chars)
     * - Raw PEM format with -----BEGIN/END----- markers
     * - PEM with escaped newlines (\n or \\n)
     *
     * @param  string  $keyData  The key in any supported format
     * @return string PEM-formatted public key
     */
    private static function toPem(string $keyData): string
    {
        $keyData = trim($keyData);

        // Check if it's already valid PEM format
        if (str_starts_with($keyData, '-----BEGIN')) {
            // Handle escaped newlines from Docker environments
            $keyData = str_replace(['\\n', '\n', '\\\\n'], "\n", $keyData);

            return $keyData;
        }

        // Try base64 decoding - if it decodes to valid PEM, use that
        $decoded = base64_decode($keyData, true);
        if ($decoded !== false && str_starts_with($decoded, '-----BEGIN')) {
            return $decoded;
        }

        // Assume it's raw base64-encoded key material (no PEM headers)
        // Wrap it in PEM format
        return "-----BEGIN PUBLIC KEY-----\n".
               chunk_split($keyData, 64, "\n").
               '-----END PUBLIC KEY-----';
    }
}
