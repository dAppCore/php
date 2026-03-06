<?php

declare(strict_types=1);

use Core\ModuleScanner;

beforeEach(function () {
    $this->scanner = new ModuleScanner;
});

describe('extractListens', function () {
    it('extracts $listens from a class with public static property', function () {
        $listens = $this->scanner->extractListens(TestModuleWithListens::class);

        expect($listens)->toBe([
            'SomeEvent' => 'handleSomeEvent',
            'AnotherEvent' => 'onAnother',
        ]);
    });

    it('returns empty array when class has no $listens property', function () {
        $listens = $this->scanner->extractListens(TestModuleWithoutListens::class);

        expect($listens)->toBe([]);
    });

    it('returns empty array when $listens is not public', function () {
        $listens = $this->scanner->extractListens(TestModuleWithPrivateListens::class);

        expect($listens)->toBe([]);
    });

    it('returns empty array when $listens is not static', function () {
        $listens = $this->scanner->extractListens(TestModuleWithNonStaticListens::class);

        expect($listens)->toBe([]);
    });

    it('returns empty array when $listens is not an array', function () {
        $listens = $this->scanner->extractListens(TestModuleWithStringListens::class);

        expect($listens)->toBe([]);
    });

    it('returns empty array for non-existent class', function () {
        $listens = $this->scanner->extractListens('NonExistentClass');

        expect($listens)->toBe([]);
    });
});

describe('scan', function () {
    it('skips non-existent directories', function () {
        $result = $this->scanner->scan(['/path/that/does/not/exist']);

        expect($result)->toBe([]);
    });
});

// Test fixtures - these classes are used to test reflection behaviour

class TestModuleWithListens
{
    public static array $listens = [
        'SomeEvent' => 'handleSomeEvent',
        'AnotherEvent' => 'onAnother',
    ];
}

class TestModuleWithoutListens
{
    public function boot(): void {}
}

class TestModuleWithPrivateListens
{
    private static array $listens = [
        'SomeEvent' => 'handleSomeEvent',
    ];
}

class TestModuleWithNonStaticListens
{
    public array $listens = [
        'SomeEvent' => 'handleSomeEvent',
    ];
}

class TestModuleWithStringListens
{
    public static string $listens = 'not an array';
}
