<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Crypt;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Cast for storing encrypted array data as ArrayObject.
 *
 * This cast encrypts array data before storing it in the database and decrypts
 * it when retrieving. Useful for sensitive configuration data like API credentials.
 */
class EncryptArrayObject implements CastsAttributes
{
    /**
     * Cast the given value to an ArrayObject.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     */
    public function get($model, string $key, $value, array $attributes): ?ArrayObject
    {
        if (isset($attributes[$key])) {
            try {
                $decrypted = Crypt::decryptString($attributes[$key]);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                Log::warning('Failed to decrypt array object', ['key' => $key, 'error' => $e->getMessage()]);

                return null;
            }

            $decoded = json_decode($decrypted, true);

            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to decode encrypted array', ['key' => $key, 'error' => json_last_error_msg()]);

                return null;
            }

            return new ArrayObject($decoded ?? []);
        }

        return null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>|null
     */
    public function set($model, string $key, $value, array $attributes): ?array
    {
        if (! is_null($value)) {
            $encoded = json_encode($value);

            if ($encoded === false) {
                throw new \RuntimeException(
                    "Failed to encode value for encryption [{$key}]: ".json_last_error_msg()
                );
            }

            $encrypted = Crypt::encryptString($encoded);

            return [$key => $encrypted];
        }

        return null;
    }
}
