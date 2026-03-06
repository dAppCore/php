<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Front\Admin\AdminMenuRegistry;
use Core\Front\Admin\Concerns\HasMenuPermissions;
use Core\Front\Admin\Contracts\AdminMenuProvider;
use Core\Tenant\Services\EntitlementService;
use Core\Tests\TestCase;
use Mockery;

class AdminMenuRegistryTest extends TestCase
{
    protected AdminMenuRegistry $registry;

    protected EntitlementService $entitlements;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entitlements = Mockery::mock(EntitlementService::class);
        $this->registry = new AdminMenuRegistry($this->entitlements);
        $this->registry->setCachingEnabled(false);
    }

    public function test_build_returns_empty_array_when_no_providers_registered(): void
    {
        $menu = $this->registry->build(null);

        $this->assertIsArray($menu);
        $this->assertEmpty($menu);
    }

    public function test_register_adds_provider(): void
    {
        $provider = $this->createMockProvider([
            [
                'group' => 'services',
                'priority' => 10,
                'item' => fn () => ['label' => 'Test Service', 'icon' => 'cog', 'href' => '/test'],
            ],
        ]);

        $this->registry->register($provider);
        $menu = $this->registry->build(null);

        $this->assertNotEmpty($menu);
    }

    public function test_build_groups_items_into_predefined_groups(): void
    {
        $provider = $this->createMockProvider([
            [
                'group' => 'dashboard',
                'priority' => 10,
                'item' => fn () => ['label' => 'Dashboard', 'icon' => 'home', 'href' => '/'],
            ],
            [
                'group' => 'services',
                'priority' => 10,
                'item' => fn () => ['label' => 'Commerce', 'icon' => 'cart', 'href' => '/commerce'],
            ],
        ]);

        $this->registry->register($provider);
        $menu = $this->registry->build(null);

        // Dashboard is standalone, so items appear directly
        // Services becomes a dropdown parent
        $this->assertNotEmpty($menu);
    }

    public function test_build_sorts_items_by_priority(): void
    {
        $provider = $this->createMockProvider([
            [
                'group' => 'dashboard',
                'priority' => 30,
                'item' => fn () => ['label' => 'Third', 'icon' => 'cog', 'href' => '/third'],
            ],
            [
                'group' => 'dashboard',
                'priority' => 10,
                'item' => fn () => ['label' => 'First', 'icon' => 'home', 'href' => '/first'],
            ],
            [
                'group' => 'dashboard',
                'priority' => 20,
                'item' => fn () => ['label' => 'Second', 'icon' => 'star', 'href' => '/second'],
            ],
        ]);

        $this->registry->register($provider);
        $menu = $this->registry->build(null);

        // Dashboard is standalone, items should be sorted by priority
        $labels = array_column($menu, 'label');
        $this->assertEquals(['First', 'Second', 'Third'], $labels);
    }

    public function test_build_skips_items_returning_null(): void
    {
        $provider = $this->createMockProvider([
            [
                'group' => 'dashboard',
                'priority' => 10,
                'item' => fn () => ['label' => 'Visible', 'icon' => 'eye', 'href' => '/visible'],
            ],
            [
                'group' => 'dashboard',
                'priority' => 20,
                'item' => fn () => null,
            ],
        ]);

        $this->registry->register($provider);
        $menu = $this->registry->build(null);

        $this->assertCount(1, $menu);
        $this->assertEquals('Visible', $menu[0]['label']);
    }

    public function test_get_groups_returns_predefined_group_keys(): void
    {
        $groups = $this->registry->getGroups();

        $this->assertIsArray($groups);
        $this->assertContains('dashboard', $groups);
        $this->assertContains('workspaces', $groups);
        $this->assertContains('services', $groups);
        $this->assertContains('settings', $groups);
        $this->assertContains('admin', $groups);
    }

    public function test_get_group_config_returns_config_for_known_group(): void
    {
        $config = $this->registry->getGroupConfig('settings');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('label', $config);
        $this->assertEquals('Account', $config['label']);
    }

    public function test_get_group_config_returns_empty_for_unknown_group(): void
    {
        $config = $this->registry->getGroupConfig('nonexistent');

        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    public function test_multiple_providers_can_be_registered(): void
    {
        $provider1 = $this->createMockProvider([
            [
                'group' => 'dashboard',
                'priority' => 10,
                'item' => fn () => ['label' => 'From Provider 1', 'icon' => 'one', 'href' => '/one'],
            ],
        ]);

        $provider2 = $this->createMockProvider([
            [
                'group' => 'dashboard',
                'priority' => 20,
                'item' => fn () => ['label' => 'From Provider 2', 'icon' => 'two', 'href' => '/two'],
            ],
        ]);

        $this->registry->register($provider1);
        $this->registry->register($provider2);

        $menu = $this->registry->build(null);
        $labels = array_column($menu, 'label');

        $this->assertContains('From Provider 1', $labels);
        $this->assertContains('From Provider 2', $labels);
    }

    public function test_build_uses_default_priority_when_not_specified(): void
    {
        $provider = $this->createMockProvider([
            [
                'group' => 'dashboard',
                'item' => fn () => ['label' => 'No Priority', 'icon' => 'default', 'href' => '/default'],
            ],
            [
                'group' => 'dashboard',
                'priority' => 100,
                'item' => fn () => ['label' => 'Low Priority', 'icon' => 'low', 'href' => '/low'],
            ],
            [
                'group' => 'dashboard',
                'priority' => 10,
                'item' => fn () => ['label' => 'High Priority', 'icon' => 'high', 'href' => '/high'],
            ],
        ]);

        $this->registry->register($provider);
        $menu = $this->registry->build(null);

        // Default priority is 50, so order should be: High (10), No (50), Low (100)
        $labels = array_column($menu, 'label');
        $this->assertEquals(['High Priority', 'No Priority', 'Low Priority'], $labels);
    }

    public function test_build_adds_dividers_between_groups(): void
    {
        $provider = $this->createMockProvider([
            [
                'group' => 'dashboard',
                'priority' => 10,
                'item' => fn () => ['label' => 'Dashboard Item', 'icon' => 'home', 'href' => '/'],
            ],
            [
                'group' => 'services',
                'priority' => 10,
                'item' => fn () => ['label' => 'Service Item', 'icon' => 'cog', 'href' => '/service'],
            ],
        ]);

        $this->registry->register($provider);
        $menu = $this->registry->build(null);

        // Should have a divider between dashboard and services
        $hasDivider = false;
        foreach ($menu as $item) {
            if (isset($item['divider']) && $item['divider'] === true) {
                $hasDivider = true;
                break;
            }
        }
        $this->assertTrue($hasDivider);
    }

    public function test_build_creates_dropdown_for_non_standalone_groups(): void
    {
        $provider = $this->createMockProvider([
            [
                'group' => 'settings',
                'priority' => 10,
                'item' => fn () => ['label' => 'Profile', 'icon' => 'user', 'href' => '/profile'],
            ],
            [
                'group' => 'settings',
                'priority' => 20,
                'item' => fn () => ['label' => 'Security', 'icon' => 'lock', 'href' => '/security'],
            ],
        ]);

        $this->registry->register($provider);
        $menu = $this->registry->build(null);

        // Settings is not standalone, should create a dropdown
        $settingsDropdown = null;
        foreach ($menu as $item) {
            if (isset($item['label']) && $item['label'] === 'Account') {
                $settingsDropdown = $item;
                break;
            }
        }

        $this->assertNotNull($settingsDropdown);
        $this->assertArrayHasKey('children', $settingsDropdown);
        $this->assertCount(2, $settingsDropdown['children']);
    }

    protected function createMockProvider(array $items): AdminMenuProvider
    {
        return new class($items) implements AdminMenuProvider
        {
            use HasMenuPermissions;

            public function __construct(private array $items) {}

            public function adminMenuItems(): array
            {
                return $this->items;
            }
        };
    }
}
