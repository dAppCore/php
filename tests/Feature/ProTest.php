<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Pro;
use Core\Tests\TestCase;

class ProTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Pro::clearCache();
    }

    protected function tearDown(): void
    {
        Pro::clearCache();
        parent::tearDown();
    }

    public function test_has_flux_pro_returns_false_when_not_installed(): void
    {
        // In tests, livewire/flux-pro is not installed
        $this->assertFalse(Pro::hasFluxPro());
    }

    public function test_has_font_awesome_pro_returns_false_by_default(): void
    {
        $this->assertFalse(Pro::hasFontAwesomePro());
    }

    public function test_has_font_awesome_pro_returns_true_when_configured(): void
    {
        config(['core.fontawesome.pro' => true]);
        Pro::clearCache();

        $this->assertTrue(Pro::hasFontAwesomePro());
    }

    public function test_flux_pro_components_returns_expected_components(): void
    {
        $components = Pro::fluxProComponents();

        $this->assertContains('calendar', $components);
        $this->assertContains('editor', $components);
        $this->assertContains('chart', $components);
        $this->assertContains('kanban', $components);
    }

    public function test_requires_flux_pro_returns_true_for_pro_components(): void
    {
        $this->assertTrue(Pro::requiresFluxPro('calendar'));
        $this->assertTrue(Pro::requiresFluxPro('editor'));
        $this->assertTrue(Pro::requiresFluxPro('chart'));
    }

    public function test_requires_flux_pro_returns_false_for_free_components(): void
    {
        $this->assertFalse(Pro::requiresFluxPro('button'));
        $this->assertFalse(Pro::requiresFluxPro('input'));
        $this->assertFalse(Pro::requiresFluxPro('modal'));
    }

    public function test_requires_flux_pro_normalizes_component_prefix(): void
    {
        $this->assertTrue(Pro::requiresFluxPro('core:calendar'));
        $this->assertTrue(Pro::requiresFluxPro('flux:editor'));
    }

    public function test_requires_flux_pro_handles_dot_notation(): void
    {
        $this->assertTrue(Pro::requiresFluxPro('calendar.month'));
        $this->assertTrue(Pro::requiresFluxPro('core:calendar.week'));
    }

    public function test_require_flux_pro_throws_exception_in_test_environment(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Flux Pro.*requires a licence/');

        Pro::requireFluxPro('test-component');
    }

    public function test_require_flux_pro_includes_component_name_in_exception(): void
    {
        try {
            Pro::requireFluxPro('core:calendar');
            $this->fail('Expected exception not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('core:calendar', $e->getMessage());
            $this->assertStringContainsString('fluxui.dev/pricing', $e->getMessage());
        }
    }

    public function test_font_awesome_styles_returns_pro_styles_when_pro_enabled(): void
    {
        config(['core.fontawesome.pro' => true]);
        Pro::clearCache();

        $styles = Pro::fontAwesomeStyles();

        $this->assertContains('light', $styles);
        $this->assertContains('thin', $styles);
        $this->assertContains('duotone', $styles);
        $this->assertContains('sharp', $styles);
    }

    public function test_font_awesome_styles_returns_free_styles_when_pro_disabled(): void
    {
        config(['core.fontawesome.pro' => false]);
        Pro::clearCache();

        $styles = Pro::fontAwesomeStyles();

        $this->assertContains('solid', $styles);
        $this->assertContains('regular', $styles);
        $this->assertContains('brands', $styles);
        $this->assertNotContains('light', $styles);
        $this->assertNotContains('duotone', $styles);
    }

    public function test_font_awesome_fallback_returns_original_style_when_pro(): void
    {
        config(['core.fontawesome.pro' => true]);
        Pro::clearCache();

        $this->assertEquals('light', Pro::fontAwesomeFallback('light'));
        $this->assertEquals('thin', Pro::fontAwesomeFallback('thin'));
        $this->assertEquals('duotone', Pro::fontAwesomeFallback('duotone'));
    }

    public function test_font_awesome_fallback_returns_fallback_when_free(): void
    {
        config(['core.fontawesome.pro' => false]);
        Pro::clearCache();

        $this->assertEquals('regular', Pro::fontAwesomeFallback('light'));
        $this->assertEquals('regular', Pro::fontAwesomeFallback('thin'));
        $this->assertEquals('solid', Pro::fontAwesomeFallback('duotone'));
        $this->assertEquals('solid', Pro::fontAwesomeFallback('sharp'));
    }

    public function test_font_awesome_fallback_preserves_free_styles(): void
    {
        config(['core.fontawesome.pro' => false]);
        Pro::clearCache();

        $this->assertEquals('solid', Pro::fontAwesomeFallback('solid'));
        $this->assertEquals('regular', Pro::fontAwesomeFallback('regular'));
        $this->assertEquals('brands', Pro::fontAwesomeFallback('brands'));
    }

    public function test_clear_cache_resets_detection(): void
    {
        // First call caches the result
        Pro::hasFluxPro();
        Pro::hasFontAwesomePro();

        // Clear and reconfigure
        Pro::clearCache();
        config(['core.fontawesome.pro' => true]);

        // Should reflect new config
        $this->assertTrue(Pro::hasFontAwesomePro());
    }
}
