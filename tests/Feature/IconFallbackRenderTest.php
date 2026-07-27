<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Pro;
use Core\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

/**
 * Renders <core:icon> for real, rather than asserting on the resolver alone.
 *
 * The component is used ~350 times across the estate, and its two failure
 * modes are both silent: a Pro class on a free kit renders nothing, and an
 * FA7 family missing its weight class renders as classic. Neither raises an
 * error, so only the produced markup can catch them.
 */
class IconFallbackRenderTest extends TestCase
{
    /**
     * The base TestCase loads only LifecycleEventProvider, so the <core:> tag
     * compiler is absent and tags pass through as literal text — which reads
     * as a content assertion failure rather than "the component never ran".
     */
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            \Core\Front\Components\Boot::class,
        ]);
    }

    protected function tearDown(): void
    {
        Pro::clearCache();
        parent::tearDown();
    }

    protected function withPro(bool $enabled): void
    {
        config(['core.fontawesome.pro' => $enabled]);
        Pro::clearCache();
    }

    public function test_free_kit_swaps_in_the_named_fallback_icon(): void
    {
        $this->withPro(false);

        $html = Blade::render('<core:icon name="face-viewfinder" style="duotone" fallback="camera" />');

        $this->assertStringContainsString('fa-camera', $html);
        $this->assertStringNotContainsString('fa-face-viewfinder', $html);
    }

    public function test_pro_kit_keeps_the_requested_icon(): void
    {
        $this->withPro(true);

        $html = Blade::render('<core:icon name="face-viewfinder" style="duotone" fallback="camera" />');

        $this->assertStringContainsString('fa-face-viewfinder', $html);
        $this->assertStringContainsString('fa-duotone', $html);
    }

    public function test_jelly_carries_its_weight_class_so_the_family_binds(): void
    {
        $this->withPro(true);

        // "fa-jelly fa-globe" alone renders as classic — the weight class is
        // what binds the family in FontAwesome 7.
        $html = Blade::render('<core:icon name="globe" />');

        $this->assertStringContainsString('fa-jelly', $html);
        $this->assertStringContainsString('fa-regular', $html);
    }

    public function test_jelly_degrades_to_solid_without_pro(): void
    {
        $this->withPro(false);

        $html = Blade::render('<core:icon name="globe" />');

        $this->assertStringContainsString('fa-solid', $html);
        $this->assertStringNotContainsString('fa-jelly', $html);
    }

    public function test_brands_are_never_downgraded(): void
    {
        $this->withPro(false);

        $html = Blade::render('<core:icon name="github" />');

        $this->assertStringContainsString('fa-brands', $html);
        $this->assertStringContainsString('fa-github', $html);
    }

    public function test_an_icon_without_a_fallback_still_renders_something(): void
    {
        $this->withPro(false);

        $html = Blade::render('<core:icon name="house" />');

        $this->assertStringContainsString('fa-solid', $html);
        $this->assertStringContainsString('fa-house', $html);
    }
}
