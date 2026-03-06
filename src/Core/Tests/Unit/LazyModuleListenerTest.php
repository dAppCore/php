<?php

declare(strict_types=1);

use Core\LazyModuleListener;

describe('LazyModuleListener', function () {
    it('stores module class and method', function () {
        $listener = new LazyModuleListener(
            TestLazyModule::class,
            'handleEvent'
        );

        expect($listener->getModuleClass())->toBe(TestLazyModule::class);
        expect($listener->getMethod())->toBe('handleEvent');
    });

    it('invokes the module method when called', function () {
        TestLazyModule::$called = false;
        TestLazyModule::$receivedEvent = null;

        $listener = new LazyModuleListener(
            TestLazyModule::class,
            'handleEvent'
        );

        $event = new TestEvent('test data');
        $listener($event);

        expect(TestLazyModule::$called)->toBeTrue();
        expect(TestLazyModule::$receivedEvent)->toBe($event);
    });

    it('reuses the same module instance on multiple calls', function () {
        TestLazyModule::$instanceCount = 0;

        $listener = new LazyModuleListener(
            TestLazyModule::class,
            'handleEvent'
        );

        $event = new TestEvent('test');
        $listener($event);
        $listener($event);
        $listener($event);

        expect(TestLazyModule::$instanceCount)->toBe(1);
    });

    it('handle method is alias for __invoke', function () {
        TestLazyModule::$called = false;
        TestLazyModule::$receivedEvent = null;

        $listener = new LazyModuleListener(
            TestLazyModule::class,
            'handleEvent'
        );

        $event = new TestEvent('handle test');
        $listener->handle($event);

        expect(TestLazyModule::$called)->toBeTrue();
        expect(TestLazyModule::$receivedEvent)->toBe($event);
    });
});

// Test fixtures

class TestEvent
{
    public function __construct(public string $data) {}
}

class TestLazyModule
{
    public static bool $called = false;

    public static ?TestEvent $receivedEvent = null;

    public static int $instanceCount = 0;

    public function __construct()
    {
        self::$instanceCount++;
    }

    public function handleEvent(TestEvent $event): void
    {
        self::$called = true;
        self::$receivedEvent = $event;
    }
}
