<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

namespace Core\Tests\Unit;

use Core\Helpers\HadesEncrypt;
use PHPUnit\Framework\TestCase;

class HadesEncryptTest extends TestCase
{
    private string $testPublicKey = <<<'PEM'
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

    public function test_public_key_can_be_parsed(): void
    {
        $key = openssl_pkey_get_public($this->testPublicKey);
        $this->assertNotFalse($key, 'Failed to parse public key: '.openssl_error_string());

        $details = openssl_pkey_get_details($key);
        $this->assertEquals(3072, $details['bits'], 'Expected 3072-bit RSA key');
    }

    public function test_encrypt_returns_base64_when_key_set(): void
    {
        // Set the env var
        $_ENV['HADES_PUBLIC_KEY'] = $this->testPublicKey;

        $exception = new \Exception('Test error message');
        $encrypted = HadesEncrypt::encrypt($exception);

        $this->assertNotNull($encrypted, 'Encryption returned null');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $encrypted, 'Not valid base64');

        // Verify it's decodable
        $decoded = base64_decode($encrypted);
        $this->assertNotFalse($decoded, 'Base64 decode failed');
        $this->assertGreaterThan(100, strlen($decoded), 'Decoded payload too short');
    }

    public function test_encrypt_uses_default_key_when_no_env(): void
    {
        // Clear all env var sources
        unset($_ENV['HADES_PUBLIC_KEY']);
        unset($_SERVER['HADES_PUBLIC_KEY']);
        putenv('HADES_PUBLIC_KEY');

        $exception = new \Exception('Test error');
        $encrypted = HadesEncrypt::encrypt($exception);

        // Should still work using the hardcoded default key
        $this->assertNotNull($encrypted, 'Should use default hardcoded key');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\\/=]+$/', $encrypted);
    }

    public function test_to_html_comment_format(): void
    {
        $_ENV['HADES_PUBLIC_KEY'] = $this->testPublicKey;

        $exception = new \Exception('Test error');
        $comment = HadesEncrypt::toHtmlComment($exception);

        // Comment includes explanation text and HADES marker
        $this->assertStringContainsString('<!--', $comment);
        $this->assertStringContainsString('HADES:', $comment);
        $this->assertStringEndsWith(" -->\n", $comment);
    }

    public function test_handles_escaped_newlines_in_key(): void
    {
        // Simulate how Docker environments might pass the key with literal \n
        $keyWithEscapedNewlines = str_replace("\n", '\\n', $this->testPublicKey);
        $_ENV['HADES_PUBLIC_KEY'] = $keyWithEscapedNewlines;

        $exception = new \Exception('Test error');
        $encrypted = HadesEncrypt::encrypt($exception);

        $this->assertNotNull($encrypted, 'Should handle escaped newlines');
    }

    public function test_payload_structure(): void
    {
        $_ENV['HADES_PUBLIC_KEY'] = $this->testPublicKey;

        $exception = new \Exception('Test message', 42);
        $encrypted = HadesEncrypt::encrypt($exception);

        // Decode and verify structure
        $packed = base64_decode($encrypted);

        // First 2 bytes are key length (big-endian)
        $keyLen = unpack('n', substr($packed, 0, 2))[1];

        // For 3072-bit RSA with OAEP padding, encrypted key should be 384 bytes
        $this->assertEquals(384, $keyLen, 'Unexpected encrypted key length');

        // Total structure: 2 (len) + 384 (key) + 12 (iv) + 16 (tag) + ciphertext
        $minLength = 2 + 384 + 12 + 16 + 1;
        $this->assertGreaterThanOrEqual($minLength, strlen($packed));
    }

    public function test_handles_base64_encoded_pem(): void
    {
        // Base64 encode the entire PEM - recommended for env vars
        $base64EncodedPem = base64_encode($this->testPublicKey);
        $_ENV['HADES_PUBLIC_KEY'] = $base64EncodedPem;

        $exception = new \Exception('Test error');
        $encrypted = HadesEncrypt::encrypt($exception);

        $this->assertNotNull($encrypted, 'Should handle base64-encoded PEM key');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $encrypted, 'Not valid base64');
    }

    public function test_base64_encoded_key_is_single_line(): void
    {
        // Verify that base64 encoding the key gives us a clean single-line string
        $base64EncodedPem = base64_encode($this->testPublicKey);

        // No newlines, no special characters that would need escaping
        $this->assertStringNotContainsString("\n", $base64EncodedPem);
        $this->assertStringNotContainsString("'", $base64EncodedPem);
        $this->assertStringNotContainsString('"', $base64EncodedPem);
        $this->assertStringNotContainsString('\\', $base64EncodedPem);
    }
}
