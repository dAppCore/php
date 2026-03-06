<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Helpers\Rules\HexRule;
use Core\Mod\Social\Enums\ResourceStatus;
use Core\Tenant\Models\User;
use Core\Tenant\Rules\CheckUserPasswordRule;
use Core\Tenant\Rules\ResourceStatusRule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('CheckUserPasswordRule', function () {
    it('passes validation when password matches', function () {
        $user = new User;
        $user->password = Hash::make('correct-password');

        $rule = new CheckUserPasswordRule($user);
        $validator = Validator::make(
            ['password' => 'correct-password'],
            ['password' => $rule]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails validation when password does not match', function () {
        $user = new User;
        $user->password = Hash::make('correct-password');

        $rule = new CheckUserPasswordRule($user);
        $validator = Validator::make(
            ['password' => 'wrong-password'],
            ['password' => $rule]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('uses custom error message when provided', function () {
        $user = new User;
        $user->password = Hash::make('correct-password');

        $customMessage = 'Your current password is incorrect';
        $rule = new CheckUserPasswordRule($user, $customMessage);
        $validator = Validator::make(
            ['password' => 'wrong-password'],
            ['password' => $rule]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->first('password'))->toBe($customMessage);
    });
});

describe('HexRule', function () {
    it('passes validation for 6-digit hex codes', function () {
        $rule = new HexRule;
        $validator = Validator::make(
            ['colour' => '#ffffff'],
            ['colour' => $rule]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('passes validation for 3-digit hex codes', function () {
        $rule = new HexRule;
        $validator = Validator::make(
            ['colour' => '#fff'],
            ['colour' => $rule]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('passes validation for uppercase hex codes', function () {
        $rule = new HexRule;
        $validator = Validator::make(
            ['colour' => '#ABCDEF'],
            ['colour' => $rule]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails validation for invalid hex codes', function () {
        $rule = new HexRule;
        $validator = Validator::make(
            ['colour' => '#gggggg'],
            ['colour' => $rule]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('fails validation when hash symbol is missing', function () {
        $rule = new HexRule;
        $validator = Validator::make(
            ['colour' => 'ffffff'],
            ['colour' => $rule]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('rejects 3-digit codes when forceFull is true', function () {
        $rule = new HexRule(forceFull: true);
        $validator = Validator::make(
            ['colour' => '#fff'],
            ['colour' => $rule]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('accepts 6-digit codes when forceFull is true', function () {
        $rule = new HexRule(forceFull: true);
        $validator = Validator::make(
            ['colour' => '#ffffff'],
            ['colour' => $rule]
        );

        expect($validator->passes())->toBeTrue();
    });
});

describe('ResourceStatusRule', function () {
    it('passes validation for enabled status', function () {
        $rule = new ResourceStatusRule;
        $validator = Validator::make(
            ['status' => ResourceStatus::ENABLED->value],
            ['status' => $rule]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('passes validation for disabled status', function () {
        $rule = new ResourceStatusRule;
        $validator = Validator::make(
            ['status' => ResourceStatus::DISABLED->value],
            ['status' => $rule]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('fails validation for invalid status values', function () {
        $rule = new ResourceStatusRule;
        $validator = Validator::make(
            ['status' => 999],
            ['status' => $rule]
        );

        expect($validator->fails())->toBeTrue();
    });

    it('fails validation for string values', function () {
        $rule = new ResourceStatusRule;
        $validator = Validator::make(
            ['status' => 'enabled'],
            ['status' => $rule]
        );

        expect($validator->fails())->toBeTrue();
    });
});
