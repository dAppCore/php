<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

use Core\Crypt\LthnHash;

describe('LthnHash', function () {
    it('generates consistent vBucket IDs for the same domain', function () {
        $first = LthnHash::vBucketId('host.uk.com');
        $second = LthnHash::vBucketId('host.uk.com');

        expect($first)->toBe($second);
    });

    it('generates different vBucket IDs for different domains', function () {
        $hostUk = LthnHash::vBucketId('host.uk.com');
        $example = LthnHash::vBucketId('example.com');

        expect($hostUk)->not->toBe($example);
    });

    it('generates 64-character vBucket IDs (full SHA-256)', function () {
        $vBucketId = LthnHash::vBucketId('host.uk.com');

        expect($vBucketId)->toHaveLength(64);
    });

    it('generates 64-character full hashes (SHA-256)', function () {
        $hash = LthnHash::hash('host.uk.com');

        expect($hash)->toHaveLength(64);
    });

    it('normalises domain case', function () {
        $lower = LthnHash::vBucketId('host.uk.com');
        $upper = LthnHash::vBucketId('HOST.UK.COM');
        $mixed = LthnHash::vBucketId('Host.UK.Com');

        expect($lower)->toBe($upper)->toBe($mixed);
    });

    it('verifies hashes correctly', function () {
        $input = 'test-domain.com';
        $hash = LthnHash::hash($input);

        expect(LthnHash::verify($input, $hash))->toBeTrue();
        expect(LthnHash::verify('wrong-domain.com', $hash))->toBeFalse();
    });

    it('handles empty strings', function () {
        $hash = LthnHash::hash('');

        expect($hash)->toHaveLength(64);
    });

    it('applies character substitution correctly', function () {
        // The salt should reverse and substitute characters
        // 'test' -> reversed: 'tset' -> substituted: '7z37'
        // So hash('test') should be sha256('test' + '7z37')
        // Source spec: https://github.com/dAppServer/server/blob/main/tests/crypt/quasi-salt.test.ts
        $hash = LthnHash::hash('test');

        // Expected value from dAppServer source spec
        expect($hash)->toBe('0b4a8c1c92f26ed200b41dfb25525df7516cdae6a958943875345a3a444343a9');
    });

    it('can modify the key map at runtime', function () {
        $original = LthnHash::getKeyMap();

        // Test modification
        LthnHash::setKeyMap(['x' => 'y', 'y' => 'x']);
        $modified = LthnHash::getKeyMap();

        expect($modified)->toBe(['x' => 'y', 'y' => 'x']);

        // Restore original
        LthnHash::setKeyMap($original);
        expect(LthnHash::getKeyMap())->toBe($original);
    });

    it('generates short hash as prefix of full hash', function () {
        $full = LthnHash::hash('host.uk.com');
        $short = LthnHash::shortHash('host.uk.com');

        expect($full)->toStartWith($short);
    });
});
