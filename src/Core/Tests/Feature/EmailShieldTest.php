<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Carbon\Carbon;
use Core\Mail\EmailShield;
use Core\Mail\EmailShieldStat;
use Core\Mail\EmailValidationResult;
use Core\Mail\Rules\ValidatedEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Clear the cache before each test to ensure clean state
    Cache::forget('email_shield:disposable_domains');

    // Create a fresh instance after clearing cache
    $this->emailShield = new EmailShield;
});

// EmailValidationResult Value Object Tests
describe('EmailValidationResult', function () {
    it('creates a valid result', function () {
        $result = EmailValidationResult::valid('example.com');

        expect($result->isValid)->toBeTrue()
            ->and($result->isDisposable)->toBeFalse()
            ->and($result->domain)->toBe('example.com')
            ->and($result->passes())->toBeTrue()
            ->and($result->fails())->toBeFalse()
            ->and($result->getMessage())->toBe('Valid email address');
    });

    it('creates an invalid result', function () {
        $result = EmailValidationResult::invalid('Invalid format', 'bad-domain');

        expect($result->isValid)->toBeFalse()
            ->and($result->isDisposable)->toBeFalse()
            ->and($result->domain)->toBe('bad-domain')
            ->and($result->reason)->toBe('Invalid format')
            ->and($result->passes())->toBeFalse()
            ->and($result->fails())->toBeTrue()
            ->and($result->getMessage())->toBe('Invalid format');
    });

    it('creates a disposable result', function () {
        $result = EmailValidationResult::disposable('tempmail.com');

        expect($result->isValid)->toBeFalse()
            ->and($result->isDisposable)->toBeTrue()
            ->and($result->domain)->toBe('tempmail.com')
            ->and($result->passes())->toBeFalse()
            ->and($result->fails())->toBeTrue()
            ->and($result->getMessage())->toBe('Disposable email addresses are not allowed');
    });
});

// EmailShield Service Tests
describe('EmailShield Service', function () {
    it('validates a valid email address', function () {
        $result = $this->emailShield->validate('user@example.com');

        expect($result->isValid)->toBeTrue()
            ->and($result->isDisposable)->toBeFalse()
            ->and($result->domain)->toBe('example.com')
            ->and($result->passes())->toBeTrue();
    });

    it('rejects invalid email format', function () {
        $result = $this->emailShield->validate('not-an-email');

        expect($result->isValid)->toBeFalse()
            ->and($result->reason)->toBe('Invalid email format')
            ->and($result->passes())->toBeFalse();
    });

    it('rejects email without @ symbol', function () {
        $result = $this->emailShield->validate('userexample.com');

        expect($result->isValid)->toBeFalse()
            ->and($result->passes())->toBeFalse();
    });

    it('rejects disposable email domains', function () {
        $result = $this->emailShield->validate('user@tempmail.com');

        expect($result->isValid)->toBeFalse()
            ->and($result->isDisposable)->toBeTrue()
            ->and($result->domain)->toBe('tempmail.com')
            ->and($result->passes())->toBeFalse();
    });

    it('checks multiple disposable domains', function () {
        $disposableDomains = [
            'tempmail.com',
            'guerrillamail.com',
            '10minutemail.com',
            'mailinator.com',
            'throwaway.email',
        ];

        foreach ($disposableDomains as $domain) {
            $result = $this->emailShield->validate("user@{$domain}");

            expect($result->isDisposable)->toBeTrue()
                ->and($result->passes())->toBeFalse();
        }
    });

    it('is case-insensitive for domains', function () {
        $result1 = $this->emailShield->validate('user@TEMPMAIL.COM');
        $result2 = $this->emailShield->validate('user@TempMail.com');
        $result3 = $this->emailShield->validate('user@tempmail.com');

        expect($result1->isDisposable)->toBeTrue()
            ->and($result2->isDisposable)->toBeTrue()
            ->and($result3->isDisposable)->toBeTrue();
    });

    it('loads disposable domains from file', function () {
        $count = $this->emailShield->getDisposableDomainsCount();

        expect($count)->toBeGreaterThan(500);
    });

    it('caches disposable domains', function () {
        // First call - loads from file
        $shield1 = new EmailShield;
        $count1 = $shield1->getDisposableDomainsCount();

        // Second call - should load from cache
        $shield2 = new EmailShield;
        $count2 = $shield2->getDisposableDomainsCount();

        expect($count1)->toBe($count2)
            ->and($count1)->toBeGreaterThan(0);
    });

    it('can refresh cache', function () {
        $shield = new EmailShield;
        $count1 = $shield->getDisposableDomainsCount();

        Cache::put('email_shield:disposable_domains', ['fake.com' => true], 86400);

        $shield->refreshCache();
        $count2 = $shield->getDisposableDomainsCount();

        expect($count2)->toBe($count1)
            ->and($count2)->toBeGreaterThan(1);
    });
});

// Statistics Tracking Tests
describe('EmailShield Statistics', function () {
    it('records valid email statistics', function () {
        $this->emailShield->validate('user@example.com');

        $stat = EmailShieldStat::whereDate('date', today())->first();

        expect($stat)->not->toBeNull()
            ->and($stat->valid_count)->toBe(1)
            ->and($stat->invalid_count)->toBe(0)
            ->and($stat->disposable_count)->toBe(0);
    });

    it('records invalid email statistics', function () {
        $this->emailShield->validate('not-an-email');

        $stat = EmailShieldStat::whereDate('date', today())->first();

        expect($stat)->not->toBeNull()
            ->and($stat->valid_count)->toBe(0)
            ->and($stat->invalid_count)->toBe(1)
            ->and($stat->disposable_count)->toBe(0);
    });

    it('records disposable email statistics', function () {
        $this->emailShield->validate('user@tempmail.com');

        $stat = EmailShieldStat::whereDate('date', today())->first();

        expect($stat)->not->toBeNull()
            ->and($stat->valid_count)->toBe(0)
            ->and($stat->invalid_count)->toBe(0)
            ->and($stat->disposable_count)->toBe(1);
    });

    it('increments counters for multiple validations', function () {
        $this->emailShield->validate('user@example.com'); // valid
        $this->emailShield->validate('user@tempmail.com'); // disposable
        $this->emailShield->validate('not-an-email'); // invalid
        $this->emailShield->validate('another@example.com'); // valid

        $stat = EmailShieldStat::whereDate('date', today())->first();

        expect($stat)->not->toBeNull()
            ->and($stat->valid_count)->toBe(2)
            ->and($stat->invalid_count)->toBe(1)
            ->and($stat->disposable_count)->toBe(1);
    });
});

// EmailShieldStat Model Tests
describe('EmailShieldStat Model', function () {
    it('can increment valid count', function () {
        EmailShieldStat::incrementValid();
        EmailShieldStat::incrementValid();

        $stat = EmailShieldStat::whereDate('date', today())->first();

        expect($stat->valid_count)->toBe(2);
    });

    it('can increment invalid count', function () {
        EmailShieldStat::incrementInvalid();
        EmailShieldStat::incrementInvalid();
        EmailShieldStat::incrementInvalid();

        $stat = EmailShieldStat::whereDate('date', today())->first();

        expect($stat->invalid_count)->toBe(3);
    });

    it('can increment disposable count', function () {
        EmailShieldStat::incrementDisposable();

        $stat = EmailShieldStat::whereDate('date', today())->first();

        expect($stat->disposable_count)->toBe(1);
    });

    it('creates new record if date does not exist', function () {
        expect(EmailShieldStat::count())->toBe(0);

        EmailShieldStat::incrementValid();

        expect(EmailShieldStat::count())->toBe(1);
    });

    it('updates existing record for the same date', function () {
        EmailShieldStat::incrementValid();
        $stat1 = EmailShieldStat::whereDate('date', today())->first();

        EmailShieldStat::incrementValid();
        $stat2 = EmailShieldStat::whereDate('date', today())->first();

        expect(EmailShieldStat::count())->toBe(1)
            ->and($stat1->id)->toBe($stat2->id)
            ->and($stat2->valid_count)->toBe(2);
    });

    it('scopes to date range', function () {
        // Use unique dates far in the future to avoid conflicts with other tests
        EmailShieldStat::create(['date' => '2040-06-01', 'valid_count' => 10]);
        EmailShieldStat::create(['date' => '2040-06-02', 'valid_count' => 20]);
        EmailShieldStat::create(['date' => '2040-06-03', 'valid_count' => 30]);

        $stats = EmailShieldStat::forDateRange(
            Carbon::parse('2040-06-02'),
            Carbon::parse('2040-06-03')
        )->get();

        expect($stats)->toHaveCount(2)
            ->and($stats->pluck('valid_count')->toArray())->toContain(20, 30);
    });

    it('gets stats for date range', function () {
        // Use unique dates far in the future to avoid conflicts
        EmailShieldStat::create([
            'date' => '2040-07-01',
            'valid_count' => 10,
            'invalid_count' => 5,
            'disposable_count' => 3,
        ]);

        EmailShieldStat::create([
            'date' => '2040-07-02',
            'valid_count' => 20,
            'invalid_count' => 8,
            'disposable_count' => 2,
        ]);

        $stats = EmailShieldStat::getStatsForRange(
            Carbon::parse('2040-07-01'),
            Carbon::parse('2040-07-02')
        );

        expect($stats['total_valid'])->toBe(30)
            ->and($stats['total_invalid'])->toBe(13)
            ->and($stats['total_disposable'])->toBe(5)
            ->and($stats['total_checked'])->toBe(48);
    });
});

// ValidatedEmail Validation Rule Tests
describe('ValidatedEmail Validation Rule', function () {
    it('passes for valid email', function () {
        $validator = Validator::make(
            ['email' => 'user@example.com'],
            ['email' => [new ValidatedEmail]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails for invalid email format', function () {
        $validator = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => [new ValidatedEmail]]
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('email'))->toContain('Invalid email format');
    });

    it('blocks disposable email by default', function () {
        $validator = Validator::make(
            ['email' => 'user@tempmail.com'],
            ['email' => [new ValidatedEmail]]
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->first('email'))->toContain('Disposable email addresses are not allowed');
    });

    it('can allow disposable emails when configured', function () {
        $validator = Validator::make(
            ['email' => 'user@tempmail.com'],
            ['email' => [new ValidatedEmail(blockDisposable: false)]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails for non-string values', function () {
        $validator = Validator::make(
            ['email' => 12345],
            ['email' => [new ValidatedEmail]]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('can be combined with other validation rules', function () {
        $validator = Validator::make(
            ['email' => 'user@example.com'],
            ['email' => ['required', 'email', new ValidatedEmail]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails when required and empty', function () {
        $validator = Validator::make(
            ['email' => ''],
            ['email' => ['required', new ValidatedEmail]]
        );

        expect($validator->fails())->toBeTrue();
    });
});

// Integration Tests
describe('EmailShield Integration', function () {
    it('works with real-world valid emails', function () {
        $validEmails = [
            'john.doe@example.com',
            'jane@company.co.uk',
            'support@host.uk.com',
            'admin@example.org',
            'test.user+tag@domain.net',
        ];

        foreach ($validEmails as $email) {
            $result = $this->emailShield->validate($email);

            expect($result->passes())->toBeTrue()
                ->and($result->isDisposable)->toBeFalse();
        }
    });

    it('blocks common disposable email services', function () {
        $disposableEmails = [
            'test@tempmail.com',
            'user@guerrillamail.com',
            'spam@10minutemail.com',
            'throwaway@mailinator.com',
            'temp@trashmail.com',
        ];

        foreach ($disposableEmails as $email) {
            $result = $this->emailShield->validate($email);

            expect($result->passes())->toBeFalse()
                ->and($result->isDisposable)->toBeTrue();
        }
    });

    it('can retrieve statistics via service', function () {
        $this->emailShield->validate('user@example.com');
        $this->emailShield->validate('user@tempmail.com');

        $stats = $this->emailShield->getStats(Carbon::today(), Carbon::today());

        expect($stats['total_valid'])->toBe(1)
            ->and($stats['total_disposable'])->toBe(1)
            ->and($stats['total_checked'])->toBe(2);
    });
});
