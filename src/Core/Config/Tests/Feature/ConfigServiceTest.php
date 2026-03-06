<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Config\ConfigResolver;
use Core\Config\ConfigResult;
use Core\Config\ConfigService;
use Core\Config\Enums\ConfigType;
use Core\Config\Enums\ScopeType;
use Core\Config\Models\ConfigKey;
use Core\Config\Models\ConfigProfile;
use Core\Config\Models\ConfigResolved;
use Core\Config\Models\ConfigValue;
use Core\Tenant\Models\Workspace;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Clear hash for clean test state
    ConfigResolver::clearAll();

    // Create system profile
    $this->systemProfile = ConfigProfile::ensureSystem();

    // Create a test workspace
    $this->workspace = Workspace::factory()->create();
    $this->workspaceProfile = ConfigProfile::ensureWorkspace($this->workspace->id, $this->systemProfile->id);

    // Create test config keys
    $this->stringKey = ConfigKey::create([
        'code' => 'test.string_key',
        'type' => ConfigType::STRING,
        'category' => 'test',
        'description' => 'A test string key',
        'default_value' => 'default_string',
    ]);

    $this->boolKey = ConfigKey::create([
        'code' => 'test.bool_key',
        'type' => ConfigType::BOOL,
        'category' => 'test',
        'description' => 'A test boolean key',
        'default_value' => false,
    ]);

    $this->intKey = ConfigKey::create([
        'code' => 'test.int_key',
        'type' => ConfigType::INT,
        'category' => 'test',
        'description' => 'A test integer key',
        'default_value' => 10,
    ]);

    $this->service = app(ConfigService::class);
    $this->resolver = app(ConfigResolver::class);
});

describe('ConfigKey model', function () {
    it('creates keys with correct types', function () {
        expect($this->stringKey->type)->toBe(ConfigType::STRING);
        expect($this->boolKey->type)->toBe(ConfigType::BOOL);
        expect($this->intKey->type)->toBe(ConfigType::INT);
    });

    it('returns typed defaults', function () {
        expect($this->stringKey->getTypedDefault())->toBe('default_string');
        expect($this->boolKey->getTypedDefault())->toBe(false);
        expect($this->intKey->getTypedDefault())->toBe(10);
    });

    it('finds keys by code', function () {
        $found = ConfigKey::byCode('test.string_key');

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($this->stringKey->id);
    });
});

describe('ConfigProfile model', function () {
    it('creates system profile', function () {
        expect($this->systemProfile->scope_type)->toBe(ScopeType::SYSTEM);
        expect($this->systemProfile->scope_id)->toBeNull();
    });

    it('creates workspace profile', function () {
        expect($this->workspaceProfile->scope_type)->toBe(ScopeType::WORKSPACE);
        expect($this->workspaceProfile->scope_id)->toBe($this->workspace->id);
    });

    it('links workspace profile to system parent', function () {
        expect($this->workspaceProfile->parent_profile_id)->toBe($this->systemProfile->id);
    });
});

describe('ConfigResolver', function () {
    it('resolves to default when no value set', function () {
        $result = $this->resolver->resolve('test.string_key', null);

        expect($result->found)->toBeFalse();
        expect($result->get())->toBe('default_string');
    });

    it('resolves system value', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'system_value');

        $result = $this->resolver->resolve('test.string_key', null);

        expect($result->found)->toBeTrue();
        expect($result->get())->toBe('system_value');
        expect($result->resolvedFrom)->toBe(ScopeType::SYSTEM);
    });

    it('workspace overrides system value', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'system_value');
        ConfigValue::setValue($this->workspaceProfile->id, $this->stringKey->id, 'workspace_value');

        $result = $this->resolver->resolve('test.string_key', $this->workspace);

        expect($result->get())->toBe('workspace_value');
        expect($result->resolvedFrom)->toBe(ScopeType::WORKSPACE);
    });

    it('respects FINAL lock from system', function () {
        // Set locked value at system level
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'locked_value', locked: true);

        // Try to override at workspace level
        ConfigValue::setValue($this->workspaceProfile->id, $this->stringKey->id, 'workspace_value');

        $result = $this->resolver->resolve('test.string_key', $this->workspace);

        // Should get the locked system value
        expect($result->get())->toBe('locked_value');
        expect($result->isLocked())->toBeTrue();
        expect($result->resolvedFrom)->toBe(ScopeType::SYSTEM);
    });

    it('returns unconfigured for unknown keys', function () {
        $result = $this->resolver->resolve('nonexistent.key', null);

        expect($result->found)->toBeFalse();
        expect($result->isConfigured())->toBeFalse();
    });
});

describe('ConfigService with materialised resolution', function () {
    it('gets config value with default', function () {
        $value = $this->service->get('test.string_key', 'fallback');

        expect($value)->toBe('default_string');
    });

    it('gets config value from resolved table after prime', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'db_value');
        $this->service->prime();

        $value = $this->service->get('test.string_key');

        expect($value)->toBe('db_value');
    });

    it('reads from materialised table not source', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'original');
        $this->service->prime();

        // Update source directly (bypassing service)
        ConfigValue::where('profile_id', $this->systemProfile->id)
            ->where('key_id', $this->stringKey->id)
            ->update(['value' => json_encode('changed')]);

        // Should still return materialised value
        $value = $this->service->get('test.string_key');

        expect($value)->toBe('original');
    });

    it('updates materialised table on set', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'initial');
        $this->service->prime();

        // Set new value via service
        $this->service->set('test.string_key', 'updated', $this->systemProfile);

        // Should get new value
        $value = $this->service->get('test.string_key');

        expect($value)->toBe('updated');
    });

    it('checks if configured', function () {
        expect($this->service->isConfigured('test.string_key'))->toBeFalse();

        $this->service->set('test.string_key', 'some_value', $this->systemProfile);

        expect($this->service->isConfigured('test.string_key'))->toBeTrue();
    });

    it('checks if prefix is configured', function () {
        expect($this->service->isConfigured('test'))->toBeFalse();

        $this->service->set('test.string_key', 'value', $this->systemProfile);

        expect($this->service->isConfigured('test'))->toBeTrue();
    });

    it('locks and unlocks values', function () {
        $this->service->set('test.string_key', 'value', $this->systemProfile);
        $this->service->lock('test.string_key', $this->systemProfile);

        $result = $this->service->resolve('test.string_key');
        expect($result->isLocked())->toBeTrue();

        $this->service->unlock('test.string_key', $this->systemProfile);
        $result = $this->service->resolve('test.string_key');
        expect($result->isLocked())->toBeFalse();
    });

    it('gets all config values for scope', function () {
        $this->service->set('test.string_key', 'string_val', $this->systemProfile);
        $this->service->set('test.bool_key', true, $this->systemProfile);
        $this->service->set('test.int_key', 42, $this->systemProfile);
        $this->service->prime();

        $all = $this->service->all();

        expect($all['test.string_key'])->toBe('string_val');
        expect($all['test.bool_key'])->toBe(true);
        expect($all['test.int_key'])->toBe(42);
    });

    it('primes materialised table for workspace', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'system');
        ConfigValue::setValue($this->workspaceProfile->id, $this->stringKey->id, 'workspace');

        $this->service->prime();
        $this->service->prime($this->workspace);

        // Workspace context should get override
        $this->service->setContext($this->workspace);
        $wsValue = $this->service->get('test.string_key');
        expect($wsValue)->toBe('workspace');

        // System context should get system value
        $this->service->setContext(null);
        $sysValue = $this->service->get('test.string_key');
        expect($sysValue)->toBe('system');
    });
});

describe('ConfigResolved model', function () {
    it('stores and retrieves resolved values', function () {
        ConfigResolved::store(
            keyCode: 'test.key',
            value: 'test_value',
            type: ConfigType::STRING,
            workspaceId: null,
            channelId: null,
        );

        $resolved = ConfigResolved::lookup('test.key');

        expect($resolved)->not->toBeNull();
        expect($resolved->value)->toBe('test_value');
    });

    it('clears scope correctly', function () {
        ConfigResolved::store('key1', 'v1', ConfigType::STRING);
        ConfigResolved::store('key2', 'v2', ConfigType::STRING, workspaceId: $this->workspace->id);

        ConfigResolved::clearScope(null, null);

        expect(ConfigResolved::lookup('key1'))->toBeNull();
        expect(ConfigResolved::lookup('key2', $this->workspace->id))->not->toBeNull();
    });
});

describe('Single hash', function () {
    it('loads scope into hash on first access', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'hash_test');
        $this->service->prime();

        // Clear hash but keep DB
        ConfigResolver::clearAll();

        expect(ConfigResolver::isLoaded())->toBeFalse();
        expect(count(ConfigResolver::all()))->toBe(0);

        // First access should lazy-load entire scope
        $this->service->get('test.string_key');

        expect(ConfigResolver::isLoaded())->toBeTrue();
        expect(count(ConfigResolver::all()))->toBeGreaterThan(0);
    });

    it('subsequent reads hit hash not database', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'hash_read');
        $this->service->prime();

        // Clear and reload
        ConfigResolver::clearAll();

        // First read loads scope
        $this->service->get('test.string_key');

        // Value is now in hash
        expect(ConfigResolver::has('test.string_key'))->toBeTrue();

        // Get the value directly from hash
        $hashValue = ConfigResolver::get('test.string_key');
        expect($hashValue)->toBe('hash_read');
    });

    it('lazy primes uncached keys into hash', function () {
        // Set value but don't prime
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'lazy_prime');

        // Clear everything
        ConfigResolver::clearAll();

        // Access should compute and store in hash
        $value = $this->service->get('test.string_key');
        expect($value)->toBe('lazy_prime');

        // Now it's in hash
        expect(ConfigResolver::has('test.string_key'))->toBeTrue();
    });

    it('invalidation clears hash and database', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'to_invalidate');
        $this->service->prime();

        // Verify in hash
        expect(ConfigResolver::has('test.string_key'))->toBeTrue();

        // Invalidate
        $this->service->invalidateKey('test.string_key');

        // Cleared from hash
        expect(ConfigResolver::has('test.string_key'))->toBeFalse();
    });
});

describe('ConfigResult', function () {
    it('converts to array for serialisation', function () {
        $result = ConfigResult::found(
            key: 'test.key',
            value: 'test_value',
            type: ConfigType::STRING,
            locked: true,
            resolvedFrom: ScopeType::SYSTEM,
            profileId: 1,
        );

        $array = $result->toArray();

        expect($array['key'])->toBe('test.key');
        expect($array['value'])->toBe('test_value');
        expect($array['type'])->toBe('string');
        expect($array['locked'])->toBeTrue();
    });

    it('reconstructs from array', function () {
        $original = ConfigResult::found(
            key: 'test.key',
            value: 42,
            type: ConfigType::INT,
            locked: false,
            resolvedFrom: ScopeType::WORKSPACE,
            profileId: 5,
        );

        $reconstructed = ConfigResult::fromArray($original->toArray());

        expect($reconstructed->key)->toBe($original->key);
        expect($reconstructed->value)->toBe($original->value);
        expect($reconstructed->type)->toBe($original->type);
        expect($reconstructed->locked)->toBe($original->locked);
        expect($reconstructed->resolvedFrom)->toBe($original->resolvedFrom);
    });

    it('provides typed accessors', function () {
        $result = ConfigResult::found(
            key: 'test.key',
            value: '42',
            type: ConfigType::STRING,
            locked: false,
            resolvedFrom: ScopeType::SYSTEM,
            profileId: 1,
        );

        expect($result->string())->toBe('42');
        expect($result->int())->toBe(42);
    });

    it('supports virtual results', function () {
        $result = ConfigResult::virtual(
            key: 'bio.page.title',
            value: 'My Bio Page',
            type: ConfigType::STRING,
        );

        expect($result->isVirtual())->toBeTrue();
        expect($result->found)->toBeTrue();
        expect($result->get())->toBe('My Bio Page');
    });
});
