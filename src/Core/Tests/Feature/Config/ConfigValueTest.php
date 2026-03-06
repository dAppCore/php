<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Config\Enums\ConfigType;
use Core\Config\Enums\ScopeType;
use Core\Config\Models\Channel;
use Core\Config\Models\ConfigKey;
use Core\Config\Models\ConfigProfile;
use Core\Config\Models\ConfigResolved;
use Core\Config\Models\ConfigValue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Create essential test fixtures
    $this->systemProfile = ConfigProfile::create([
        'name' => 'System Default',
        'scope_type' => ScopeType::SYSTEM,
        'scope_id' => null,
        'priority' => 0,
    ]);

    $this->stringKey = ConfigKey::create([
        'code' => 'app.name',
        'type' => ConfigType::STRING,
        'category' => 'app',
        'default_value' => 'Default App',
    ]);

    $this->boolKey = ConfigKey::create([
        'code' => 'app.debug',
        'type' => ConfigType::BOOL,
        'category' => 'app',
        'default_value' => false,
    ]);

    $this->intKey = ConfigKey::create([
        'code' => 'app.limit',
        'type' => ConfigType::INT,
        'category' => 'app',
        'default_value' => 100,
    ]);
});

describe('findValue', function () {
    it('finds value by profile, key, and channel', function () {
        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Test App',
        ]);

        $found = ConfigValue::findValue($this->systemProfile->id, $this->stringKey->id);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($value->id);
        expect($found->value)->toBe('Test App');
    });

    it('returns null when not found', function () {
        $found = ConfigValue::findValue($this->systemProfile->id, 99999);

        expect($found)->toBeNull();
    });

    it('distinguishes between channel-scoped values', function () {
        $channel = Channel::create(['code' => 'web', 'name' => 'Web']);

        // Create value without channel
        ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Default Value',
            'channel_id' => null,
        ]);

        // Create value with channel
        ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Web Value',
            'channel_id' => $channel->id,
        ]);

        $withoutChannel = ConfigValue::findValue($this->systemProfile->id, $this->stringKey->id, null);
        $withChannel = ConfigValue::findValue($this->systemProfile->id, $this->stringKey->id, $channel->id);

        expect($withoutChannel->value)->toBe('Default Value');
        expect($withChannel->value)->toBe('Web Value');
    });
});

describe('setValue', function () {
    it('creates new value', function () {
        $result = ConfigValue::setValue(
            $this->systemProfile->id,
            $this->stringKey->id,
            'New Value'
        );

        expect($result->value)->toBe('New Value');
        expect(ConfigValue::count())->toBe(1);
    });

    it('updates existing value', function () {
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'Original');
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'Updated');

        expect(ConfigValue::count())->toBe(1);
        expect(ConfigValue::first()->value)->toBe('Updated');
    });

    it('sets locked flag', function () {
        $result = ConfigValue::setValue(
            $this->systemProfile->id,
            $this->stringKey->id,
            'Locked Value',
            locked: true
        );

        expect($result->locked)->toBeTrue();
        expect($result->isLocked())->toBeTrue();
    });

    it('sets inherited_from', function () {
        $parentProfile = ConfigProfile::create([
            'name' => 'Parent',
            'scope_type' => ScopeType::SYSTEM,
            'priority' => 10,
        ]);

        $result = ConfigValue::setValue(
            $this->systemProfile->id,
            $this->stringKey->id,
            'Inherited',
            inheritedFrom: $parentProfile->id
        );

        expect($result->inherited_from)->toBe($parentProfile->id);
        expect($result->isInherited())->toBeTrue();
    });

    it('handles channel-scoped values', function () {
        $channel = Channel::create(['code' => 'api', 'name' => 'API']);

        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'Default');
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'API Specific', channelId: $channel->id);

        expect(ConfigValue::count())->toBe(2);

        $default = ConfigValue::findValue($this->systemProfile->id, $this->stringKey->id, null);
        $apiScoped = ConfigValue::findValue($this->systemProfile->id, $this->stringKey->id, $channel->id);

        expect($default->value)->toBe('Default');
        expect($apiScoped->value)->toBe('API Specific');
    });

    it('clears resolved cache on update', function () {
        // Create a resolved entry - setValue clears where workspace_id = null for system profile
        // Note: ConfigResolved uses 0 for storage but queries use null for system scope
        ConfigResolved::create([
            'key_code' => $this->stringKey->code,
            'workspace_id' => null,
            'channel_id' => null,
            'value' => 'Old Cached',
            'type' => 'string',
            'locked' => false,
            'virtual' => false,
            'computed_at' => now(),
        ]);

        expect(ConfigResolved::count())->toBe(1);

        // Update value should clear resolved
        ConfigValue::setValue($this->systemProfile->id, $this->stringKey->id, 'New Value');

        expect(ConfigResolved::count())->toBe(0);
    });

    it('returns created value even when cache clear has nothing to do', function () {
        // Test that setValue works even when there's no resolved cache to clear
        $result = ConfigValue::setValue(
            $this->systemProfile->id,
            $this->stringKey->id,
            'New Value'
        );

        expect($result->value)->toBe('New Value');
        expect(ConfigResolved::count())->toBe(0);
    });
});

describe('getTypedValue', function () {
    it('casts string value', function () {
        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 123,
        ]);

        expect($value->getTypedValue())->toBe('123');
    });

    it('casts bool value', function () {
        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->boolKey->id,
            'value' => 'true',
        ]);

        expect($value->getTypedValue())->toBeTrue();
    });

    it('casts int value', function () {
        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->intKey->id,
            'value' => '42',
        ]);

        expect($value->getTypedValue())->toBe(42);
    });

    it('returns raw value when key relationship is null', function () {
        // Create a value with valid key, then delete the key to simulate orphaned value
        $tempKey = ConfigKey::create([
            'code' => 'temp.key',
            'type' => ConfigType::STRING,
            'category' => 'temp',
        ]);

        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $tempKey->id,
            'value' => 'raw',
        ]);

        // Delete the key to orphan the value (FK has ON DELETE CASCADE but we test the method)
        // Instead, test by clearing the relationship
        $value->setRelation('key', null);

        expect($value->getTypedValue())->toBe('raw');
    });
});

describe('forKeyInProfiles', function () {
    it('retrieves values across multiple profiles', function () {
        $profile2 = ConfigProfile::create([
            'name' => 'Profile 2',
            'scope_type' => ScopeType::WORKSPACE,
            'scope_id' => 1,
            'priority' => 10,
        ]);

        ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'System Value',
        ]);

        ConfigValue::create([
            'profile_id' => $profile2->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Workspace Value',
        ]);

        $values = ConfigValue::forKeyInProfiles(
            $this->stringKey->id,
            [$this->systemProfile->id, $profile2->id]
        );

        expect($values)->toHaveCount(2);
        expect($values->pluck('value')->all())->toContain('System Value', 'Workspace Value');
    });

    it('includes null channel values when channelIds specified', function () {
        $channel = Channel::create(['code' => 'web', 'name' => 'Web']);

        // Create value without channel
        ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Default',
            'channel_id' => null,
        ]);

        // Create value with channel
        ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Web Specific',
            'channel_id' => $channel->id,
        ]);

        $values = ConfigValue::forKeyInProfiles(
            $this->stringKey->id,
            [$this->systemProfile->id],
            [$channel->id]
        );

        // Should include both null channel and specified channel
        expect($values)->toHaveCount(2);
    });

    it('returns empty collection for non-existent profiles', function () {
        $values = ConfigValue::forKeyInProfiles($this->stringKey->id, [99999]);

        expect($values)->toHaveCount(0);
    });
});

describe('isLocked', function () {
    it('returns true when locked', function () {
        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Locked',
            'locked' => true,
        ]);

        expect($value->isLocked())->toBeTrue();
    });

    it('returns false when not locked', function () {
        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Not Locked',
            'locked' => false,
        ]);

        expect($value->isLocked())->toBeFalse();
    });
});

describe('isInherited', function () {
    it('returns true when inherited_from is set', function () {
        // Create a parent profile to inherit from
        $parentProfile = ConfigProfile::create([
            'name' => 'Parent Profile',
            'scope_type' => ScopeType::SYSTEM,
            'priority' => 10,
        ]);

        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Inherited',
            'inherited_from' => $parentProfile->id,
        ]);

        expect($value->isInherited())->toBeTrue();
    });

    it('returns false when inherited_from is null', function () {
        $value = ConfigValue::create([
            'profile_id' => $this->systemProfile->id,
            'key_id' => $this->stringKey->id,
            'value' => 'Direct',
        ]);

        expect($value->isInherited())->toBeFalse();
    });
});
