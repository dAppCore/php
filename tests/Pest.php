<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case binding
|--------------------------------------------------------------------------
|
| Pest binds closures to PHPUnit\Framework\TestCase by default, which has no
| Laravel container — so `config()`, `app()` and Blade all fail with
| "Target class [config] does not exist". That error names the container, not
| the test, which makes it read like a broken application rather than an
| unbound test case.
|
| The tests living beside the code, under src, were written against a booted application and
| were never executed, so nothing forced this binding to exist. Declaring it
| here is what turns them from dark to running.
|
*/

pest()->extend(Core\Tests\TestCase::class)->in(
    '../src/Core/Tests',
    '../src/Core/Config/Tests',
    '../src/Core/Input/Tests',
    '../src/Core/Bouncer/Tests',
    '../src/Core/Bouncer/Gate/Tests',
    '../src/Core/Front/Tests',
    '../src/Mod/Trees/Tests',
);
