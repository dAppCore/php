<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Tests\Unit\Crypt;

use Core\Crypt\EncryptArrayObject;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class EncryptArrayObjectTest extends TestCase
{
    private EncryptArrayObject $cast;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new EncryptArrayObject;
    }

    public function test_encrypts_and_decrypts_array_round_trip(): void
    {
        $model = Mockery::mock(Model::class);
        $original = ['api_key' => 'secret123', 'nested' => ['value' => true]];

        // Encrypt
        $encrypted = $this->cast->set($model, 'credentials', $original, []);
        $this->assertIsArray($encrypted);
        $this->assertArrayHasKey('credentials', $encrypted);
        $this->assertNotEquals(json_encode($original), $encrypted['credentials']);

        // Decrypt
        $decrypted = $this->cast->get($model, 'credentials', null, $encrypted);
        $this->assertInstanceOf(ArrayObject::class, $decrypted);
        $this->assertEquals($original, $decrypted->getArrayCopy());
    }

    public function test_returns_null_for_missing_attribute(): void
    {
        $model = Mockery::mock(Model::class);

        $result = $this->cast->get($model, 'credentials', null, []);

        $this->assertNull($result);
    }

    public function test_returns_null_for_null_value_on_set(): void
    {
        $model = Mockery::mock(Model::class);

        $result = $this->cast->set($model, 'credentials', null, []);

        $this->assertNull($result);
    }

    public function test_handles_empty_array(): void
    {
        $model = Mockery::mock(Model::class);

        $encrypted = $this->cast->set($model, 'credentials', [], []);
        $decrypted = $this->cast->get($model, 'credentials', null, $encrypted);

        $this->assertInstanceOf(ArrayObject::class, $decrypted);
        $this->assertEquals([], $decrypted->getArrayCopy());
    }

    public function test_handles_array_object_input(): void
    {
        $model = Mockery::mock(Model::class);
        $arrayObject = new ArrayObject(['key' => 'value']);

        $encrypted = $this->cast->set($model, 'credentials', $arrayObject, []);
        $decrypted = $this->cast->get($model, 'credentials', null, $encrypted);

        $this->assertEquals(['key' => 'value'], $decrypted->getArrayCopy());
    }

    public function test_returns_null_on_decryption_failure(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Failed to decrypt');
            });

        $model = Mockery::mock(Model::class);

        // Pass invalid encrypted data
        $result = $this->cast->get($model, 'credentials', null, [
            'credentials' => 'not-valid-encrypted-data',
        ]);

        $this->assertNull($result);
    }

    public function test_returns_null_on_json_decode_failure(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Failed to decode');
            });

        $model = Mockery::mock(Model::class);

        // Encrypt invalid JSON (raw string that isn't JSON)
        $invalidJson = Crypt::encryptString('not{valid}json');

        $result = $this->cast->get($model, 'credentials', null, [
            'credentials' => $invalidJson,
        ]);

        $this->assertNull($result);
    }

    public function test_throws_on_json_encode_failure(): void
    {
        $model = Mockery::mock(Model::class);

        // Create a value that can't be JSON encoded (resource or malformed UTF-8)
        $malformed = ['invalid' => "\xB1\x31"];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to encode value for encryption');

        $this->cast->set($model, 'credentials', $malformed, []);
    }

    public function test_handles_deeply_nested_arrays(): void
    {
        $model = Mockery::mock(Model::class);
        $nested = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep',
                    ],
                ],
            ],
        ];

        $encrypted = $this->cast->set($model, 'credentials', $nested, []);
        $decrypted = $this->cast->get($model, 'credentials', null, $encrypted);

        $this->assertEquals('deep', $decrypted['level1']['level2']['level3']['value']);
    }

    public function test_handles_unicode_content(): void
    {
        $model = Mockery::mock(Model::class);
        $unicode = [
            'name' => '李明',
            'greeting' => 'Привет',
            'emoji' => '🔐',
        ];

        $encrypted = $this->cast->set($model, 'credentials', $unicode, []);
        $decrypted = $this->cast->get($model, 'credentials', null, $encrypted);

        $this->assertEquals($unicode, $decrypted->getArrayCopy());
    }

    public function test_handles_special_characters(): void
    {
        $model = Mockery::mock(Model::class);
        $special = [
            'password' => 'p@ss!w0rd$%^&*()',
            'query' => "SELECT * FROM users WHERE name = 'test'",
        ];

        $encrypted = $this->cast->set($model, 'credentials', $special, []);
        $decrypted = $this->cast->get($model, 'credentials', null, $encrypted);

        $this->assertEquals($special, $decrypted->getArrayCopy());
    }

    public function test_handles_numeric_keys(): void
    {
        $model = Mockery::mock(Model::class);
        $indexed = ['first', 'second', 'third'];

        $encrypted = $this->cast->set($model, 'credentials', $indexed, []);
        $decrypted = $this->cast->get($model, 'credentials', null, $encrypted);

        $this->assertEquals($indexed, $decrypted->getArrayCopy());
    }
}
