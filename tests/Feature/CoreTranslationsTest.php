<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Lang\LangServiceProvider;
use Core\Tests\TestCase;
use Illuminate\Support\Facades\App;

/**
 * Regression coverage: LangServiceProvider::loadTranslations() registered
 * its hint path as __DIR__.'/en_GB', but the file lives at
 * src/Core/Lang/en_GB/core.php. loadTranslationsFrom() appends
 * "/{locale}/{group}.php" to whatever hint it is given, so Laravel looked
 * for src/Core/Lang/en_GB/en_GB/core.php — which never existed — and the
 * `core::*` namespace never resolved a single key, for any locale, in
 * production.
 */
class CoreTranslationsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            LangServiceProvider::class,
        ]);
    }

    public function test_core_namespace_translations_load_under_en_gb_locale(): void
    {
        App::setLocale('en_GB');

        $this->assertSame('Core PHP', trans('core::core.brand.name'));
    }
}
