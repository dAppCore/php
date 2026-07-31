<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Mod\Trees\Boot;
use Core\Tests\TestCase;
use Illuminate\Support\Facades\App;

/**
 * Regression coverage: Core\Mod\Trees\Boot::boot() registered its
 * translations hint as __DIR__.'/Lang/en_GB', but the file lives at
 * Lang/en_GB/trees.php. loadTranslationsFrom() appends
 * "/{locale}/{group}.php" to whatever hint it is given, so Laravel looked
 * for Lang/en_GB/en_GB/trees.php — which never existed — and the
 * `trees::*` namespace never resolved a single key, for any locale.
 */
class TreesTranslationsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            Boot::class,
        ]);
    }

    public function test_trees_namespace_translations_load_under_en_gb_locale(): void
    {
        App::setLocale('en_GB');

        $this->assertSame('Trees for Agents', trans('trees::trees.hero.badge'));
    }
}
