<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Database\Seeders\Attributes\SeederAfter;
use Core\Database\Seeders\Attributes\SeederBefore;
use Core\Database\Seeders\Exceptions\CircularDependencyException;
use Core\Database\Seeders\SeederDiscovery;
use Core\Tests\TestCase;
use Mod\Alpha\Database\Seeders\AlphaSeeder;
use Mod\Beta\Database\Seeders\BetaSeeder;
use Mod\Circular\Database\Seeders\CircularASeeder;
use Mod\Circular\Database\Seeders\CircularBSeeder;
use Mod\Gamma\Database\Seeders\DeltaSeeder;
use Mod\Gamma\Database\Seeders\GammaSeeder;

class SeederDiscoveryTest extends TestCase
{
    protected SeederDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        // Autoload the test fixtures
        $this->loadFixtures();

        $this->discovery = new SeederDiscovery(
            [$this->getFixturePath('Mod')]
        );
    }

    protected function loadFixtures(): void
    {
        // Load all seeder fixtures
        $fixtures = [
            'Mod/Alpha/Database/Seeders/AlphaSeeder.php',
            'Mod/Beta/Database/Seeders/BetaSeeder.php',
            'Mod/Gamma/Database/Seeders/GammaSeeder.php',
            'Mod/Gamma/Database/Seeders/DeltaSeeder.php',
            'Mod/Circular/Database/Seeders/CircularASeeder.php',
            'Mod/Circular/Database/Seeders/CircularBSeeder.php',
        ];

        foreach ($fixtures as $fixture) {
            $path = $this->getFixturePath($fixture);
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    public function test_discovers_seeders_from_paths(): void
    {
        // Exclude circular dependency seeders for this test
        $this->discovery->exclude([
            CircularASeeder::class,
            CircularBSeeder::class,
        ]);

        $seeders = $this->discovery->getSeeders();

        $this->assertArrayHasKey(AlphaSeeder::class, $seeders);
        $this->assertArrayHasKey(BetaSeeder::class, $seeders);
        $this->assertArrayHasKey(GammaSeeder::class, $seeders);
        $this->assertArrayHasKey(DeltaSeeder::class, $seeders);
    }

    public function test_extracts_priority_from_property(): void
    {
        $seeders = $this->discovery->getSeeders();

        $this->assertEquals(10, $seeders[AlphaSeeder::class]['priority']);
    }

    public function test_extracts_priority_from_attribute(): void
    {
        $seeders = $this->discovery->getSeeders();

        $this->assertEquals(50, $seeders[BetaSeeder::class]['priority']);
    }

    public function test_uses_default_priority_when_not_specified(): void
    {
        $seeders = $this->discovery->getSeeders();

        // CircularASeeder has no priority declaration
        $this->assertEquals(
            SeederDiscovery::DEFAULT_PRIORITY,
            $seeders[CircularASeeder::class]['priority']
        );
    }

    public function test_extracts_after_dependencies_from_property(): void
    {
        $seeders = $this->discovery->getSeeders();

        $this->assertContains(
            BetaSeeder::class,
            $seeders[GammaSeeder::class]['after']
        );
    }

    public function test_extracts_after_dependencies_from_attribute(): void
    {
        $seeders = $this->discovery->getSeeders();

        $this->assertContains(
            AlphaSeeder::class,
            $seeders[BetaSeeder::class]['after']
        );
    }

    public function test_extracts_before_dependencies_from_attribute(): void
    {
        $seeders = $this->discovery->getSeeders();

        $this->assertContains(
            BetaSeeder::class,
            $seeders[DeltaSeeder::class]['before']
        );
    }

    public function test_sorts_seeders_by_priority(): void
    {
        // Create discovery with only priority-based seeders (no dependencies)
        $discovery = new SeederDiscovery(
            [$this->getFixturePath('Mod')],
            [
                BetaSeeder::class,
                GammaSeeder::class,
                DeltaSeeder::class,
                CircularASeeder::class,
                CircularBSeeder::class,
            ]
        );

        $ordered = $discovery->discover();

        // Only AlphaSeeder should remain
        $this->assertCount(1, $ordered);
        $this->assertEquals(AlphaSeeder::class, $ordered[0]);
    }

    public function test_respects_dependency_ordering(): void
    {
        $this->discovery->exclude([
            CircularASeeder::class,
            CircularBSeeder::class,
        ]);

        $ordered = $this->discovery->discover();

        $alphaIndex = array_search(AlphaSeeder::class, $ordered);
        $betaIndex = array_search(BetaSeeder::class, $ordered);
        $gammaIndex = array_search(GammaSeeder::class, $ordered);

        // Alpha must come before Beta (Beta has SeederAfter(Alpha))
        $this->assertLessThan($betaIndex, $alphaIndex, 'Alpha should run before Beta');

        // Beta must come before Gamma (Gamma has $after = [Beta])
        $this->assertLessThan($gammaIndex, $betaIndex, 'Beta should run before Gamma');
    }

    public function test_respects_before_dependency(): void
    {
        $this->discovery->exclude([
            CircularASeeder::class,
            CircularBSeeder::class,
        ]);

        $ordered = $this->discovery->discover();

        $deltaIndex = array_search(DeltaSeeder::class, $ordered);
        $betaIndex = array_search(BetaSeeder::class, $ordered);

        // Delta must come before Beta (Delta has SeederBefore(Beta))
        $this->assertLessThan($betaIndex, $deltaIndex, 'Delta should run before Beta');
    }

    public function test_detects_circular_dependencies(): void
    {
        $discovery = new SeederDiscovery(
            [$this->getFixturePath('Mod/Circular')]
        );

        $this->expectException(CircularDependencyException::class);

        $discovery->discover();
    }

    public function test_circular_dependency_exception_contains_cycle(): void
    {
        $discovery = new SeederDiscovery(
            [$this->getFixturePath('Mod/Circular')]
        );

        try {
            $discovery->discover();
            $this->fail('Expected CircularDependencyException was not thrown');
        } catch (CircularDependencyException $e) {
            $this->assertNotEmpty($e->cycle);
            $this->assertGreaterThanOrEqual(2, count($e->cycle));
        }
    }

    public function test_exclusion_filter_works(): void
    {
        $this->discovery->exclude([
            AlphaSeeder::class,
            CircularASeeder::class,
            CircularBSeeder::class,
        ]);

        $seeders = $this->discovery->getSeeders();

        $this->assertArrayNotHasKey(AlphaSeeder::class, $seeders);
    }

    public function test_add_paths_appends_to_existing(): void
    {
        $discovery = new SeederDiscovery([]);

        $discovery->addPaths([$this->getFixturePath('Mod/Alpha')]);
        $discovery->addPaths([$this->getFixturePath('Mod/Beta')]);

        $seeders = $discovery->getSeeders();

        $this->assertArrayHasKey(AlphaSeeder::class, $seeders);
        $this->assertArrayHasKey(BetaSeeder::class, $seeders);
    }

    public function test_set_paths_replaces_existing(): void
    {
        $discovery = new SeederDiscovery([$this->getFixturePath('Mod/Alpha')]);
        $discovery->getSeeders(); // Trigger discovery

        $discovery->setPaths([$this->getFixturePath('Mod/Beta')]);
        $seeders = $discovery->getSeeders();

        $this->assertArrayNotHasKey(AlphaSeeder::class, $seeders);
        $this->assertArrayHasKey(BetaSeeder::class, $seeders);
    }

    public function test_reset_clears_cache(): void
    {
        $this->discovery->exclude([
            CircularASeeder::class,
            CircularBSeeder::class,
        ]);

        $seeders1 = $this->discovery->getSeeders();
        $this->assertNotEmpty($seeders1);

        $this->discovery->reset();
        $this->discovery->setPaths([]);

        $seeders2 = $this->discovery->getSeeders();
        $this->assertEmpty($seeders2);
    }
}
