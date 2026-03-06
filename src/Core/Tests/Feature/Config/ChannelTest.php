<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Config\Models\Channel;
use Illuminate\Support\Facades\Log;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Channel', function () {
    describe('inheritanceChain', function () {
        it('returns single channel when no parent', function () {
            $channel = Channel::create([
                'code' => 'web',
                'name' => 'Web',
            ]);

            $chain = $channel->inheritanceChain();

            expect($chain)->toHaveCount(1);
            expect($chain->first()->code)->toBe('web');
        });

        it('builds chain from child to parent', function () {
            $parent = Channel::create([
                'code' => 'social',
                'name' => 'Social',
            ]);

            $child = Channel::create([
                'code' => 'instagram',
                'name' => 'Instagram',
                'parent_id' => $parent->id,
            ]);

            $chain = $child->inheritanceChain();

            expect($chain)->toHaveCount(2);
            expect($chain[0]->code)->toBe('instagram');
            expect($chain[1]->code)->toBe('social');
        });

        it('builds multi-level chain', function () {
            $grandparent = Channel::create([
                'code' => 'digital',
                'name' => 'Digital',
            ]);

            $parent = Channel::create([
                'code' => 'social',
                'name' => 'Social',
                'parent_id' => $grandparent->id,
            ]);

            $child = Channel::create([
                'code' => 'instagram',
                'name' => 'Instagram',
                'parent_id' => $parent->id,
            ]);

            $chain = $child->inheritanceChain();

            expect($chain)->toHaveCount(3);
            expect($chain->pluck('code')->all())->toBe(['instagram', 'social', 'digital']);
        });

        it('detects and breaks circular references', function () {
            Log::shouldReceive('error')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, 'Circular reference');
                });

            // Create channels
            $channelA = Channel::create(['code' => 'a', 'name' => 'A']);
            $channelB = Channel::create(['code' => 'b', 'name' => 'B', 'parent_id' => $channelA->id]);

            // Create cycle by updating A to point to B
            $channelA->update(['parent_id' => $channelB->id]);
            $channelA->refresh();

            // Should not hang - breaks on cycle detection
            $chain = $channelA->inheritanceChain();

            expect($chain->count())->toBeLessThanOrEqual(2);
        });
    });

    describe('inheritanceCodes', function () {
        it('returns array of codes in chain', function () {
            $parent = Channel::create(['code' => 'social', 'name' => 'Social']);
            $child = Channel::create(['code' => 'instagram', 'name' => 'Instagram', 'parent_id' => $parent->id]);

            $codes = $child->inheritanceCodes();

            expect($codes)->toBe(['instagram', 'social']);
        });
    });

    describe('inheritsFrom', function () {
        it('returns true for direct parent', function () {
            $parent = Channel::create(['code' => 'social', 'name' => 'Social']);
            $child = Channel::create(['code' => 'instagram', 'name' => 'Instagram', 'parent_id' => $parent->id]);

            expect($child->inheritsFrom('social'))->toBeTrue();
        });

        it('returns true for grandparent', function () {
            $grandparent = Channel::create(['code' => 'digital', 'name' => 'Digital']);
            $parent = Channel::create(['code' => 'social', 'name' => 'Social', 'parent_id' => $grandparent->id]);
            $child = Channel::create(['code' => 'instagram', 'name' => 'Instagram', 'parent_id' => $parent->id]);

            expect($child->inheritsFrom('digital'))->toBeTrue();
        });

        it('returns true for self', function () {
            $channel = Channel::create(['code' => 'web', 'name' => 'Web']);

            expect($channel->inheritsFrom('web'))->toBeTrue();
        });

        it('returns false for unrelated channel', function () {
            $channel = Channel::create(['code' => 'web', 'name' => 'Web']);

            expect($channel->inheritsFrom('api'))->toBeFalse();
        });
    });

    describe('byCode', function () {
        it('finds system channel by code', function () {
            Channel::create(['code' => 'web', 'name' => 'Web']);

            $found = Channel::byCode('web');

            expect($found)->not->toBeNull();
            expect($found->code)->toBe('web');
        });

        it('returns null for non-existent code', function () {
            $found = Channel::byCode('nonexistent');

            expect($found)->toBeNull();
        });

        it('prefers workspace channel over system channel', function () {
            $system = Channel::create(['code' => 'web', 'name' => 'Web System']);
            $workspace = Channel::create(['code' => 'web', 'name' => 'Web Workspace', 'workspace_id' => 1]);

            $found = Channel::byCode('web', 1);

            expect($found->id)->toBe($workspace->id);
        });

        it('falls back to system channel when workspace has none', function () {
            $system = Channel::create(['code' => 'web', 'name' => 'Web System']);

            $found = Channel::byCode('web', 999);

            expect($found->id)->toBe($system->id);
        });
    });

    describe('ensure', function () {
        it('creates channel if not exists', function () {
            $channel = Channel::ensure('api', 'API');

            expect($channel->code)->toBe('api');
            expect($channel->name)->toBe('API');
            expect(Channel::where('code', 'api')->count())->toBe(1);
        });

        it('returns existing channel without updating', function () {
            $existing = Channel::create(['code' => 'api', 'name' => 'Original Name']);

            $channel = Channel::ensure('api', 'New Name');

            expect($channel->id)->toBe($existing->id);
            expect($channel->name)->toBe('Original Name');
        });

        it('creates with parent when specified', function () {
            $parent = Channel::create(['code' => 'social', 'name' => 'Social']);

            $child = Channel::ensure('instagram', 'Instagram', 'social');

            expect($child->parent_id)->toBe($parent->id);
        });

        it('creates with metadata', function () {
            $channel = Channel::ensure('api', 'API', null, null, ['version' => 'v2']);

            expect($channel->meta('version'))->toBe('v2');
        });
    });

    describe('isSystem', function () {
        it('returns true when workspace_id is null', function () {
            $channel = Channel::create(['code' => 'web', 'name' => 'Web']);

            expect($channel->isSystem())->toBeTrue();
        });

        it('returns false when workspace_id is set', function () {
            $channel = Channel::create(['code' => 'web', 'name' => 'Web', 'workspace_id' => 1]);

            expect($channel->isSystem())->toBeFalse();
        });
    });

    describe('meta', function () {
        it('returns metadata value', function () {
            $channel = Channel::create([
                'code' => 'api',
                'name' => 'API',
                'metadata' => ['rate_limit' => 100],
            ]);

            expect($channel->meta('rate_limit'))->toBe(100);
        });

        it('returns default for missing key', function () {
            $channel = Channel::create(['code' => 'api', 'name' => 'API']);

            expect($channel->meta('missing', 'default'))->toBe('default');
        });

        it('supports dot notation for nested values', function () {
            $channel = Channel::create([
                'code' => 'api',
                'name' => 'API',
                'metadata' => ['limits' => ['rate' => 100]],
            ]);

            expect($channel->meta('limits.rate'))->toBe(100);
        });
    });
});
