<?php

declare(strict_types=1);

namespace Core\Tests;

use Core\LifecycleEventProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LifecycleEventProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Override the default scan paths to use test fixtures
        $app['config']->set('app.path', $this->getFixturePath());
    }

    protected function getFixturePath(string $path = ''): string
    {
        return __DIR__.'/Fixtures'.($path ? "/{$path}" : '');
    }
}
