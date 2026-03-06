<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Database\Seeders\Exceptions\CircularDependencyException;
use Core\Database\Seeders\SeederDiscovery;
use Core\Database\Seeders\SeederRegistry;
use Core\Tests\TestCase;

class SeederRegistryTest extends TestCase
{
    protected SeederRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SeederRegistry;
    }

    public function test_register_adds_seeder(): void
    {
        $this->registry->register('App\Database\Seeders\TestSeeder');

        $this->assertTrue($this->registry->has('App\Database\Seeders\TestSeeder'));
    }

    public function test_register_with_priority(): void
    {
        $this->registry->register('App\Database\Seeders\TestSeeder', priority: 10);

        $all = $this->registry->all();

        $this->assertEquals(10, $all['App\Database\Seeders\TestSeeder']['priority']);
    }

    public function test_register_with_after_dependency(): void
    {
        $this->registry->register(
            'App\Database\Seeders\TestSeeder',
            after: ['App\Database\Seeders\FirstSeeder']
        );

        $all = $this->registry->all();

        $this->assertContains(
            'App\Database\Seeders\FirstSeeder',
            $all['App\Database\Seeders\TestSeeder']['after']
        );
    }

    public function test_register_with_before_dependency(): void
    {
        $this->registry->register(
            'App\Database\Seeders\TestSeeder',
            before: ['App\Database\Seeders\LastSeeder']
        );

        $all = $this->registry->all();

        $this->assertContains(
            'App\Database\Seeders\LastSeeder',
            $all['App\Database\Seeders\TestSeeder']['before']
        );
    }

    public function test_register_many_with_priorities(): void
    {
        $this->registry->registerMany([
            'App\Database\Seeders\FirstSeeder' => 10,
            'App\Database\Seeders\SecondSeeder' => 20,
            'App\Database\Seeders\ThirdSeeder' => 30,
        ]);

        $all = $this->registry->all();

        $this->assertCount(3, $all);
        $this->assertEquals(10, $all['App\Database\Seeders\FirstSeeder']['priority']);
        $this->assertEquals(20, $all['App\Database\Seeders\SecondSeeder']['priority']);
        $this->assertEquals(30, $all['App\Database\Seeders\ThirdSeeder']['priority']);
    }

    public function test_register_many_with_full_config(): void
    {
        $this->registry->registerMany([
            'App\Database\Seeders\FirstSeeder' => [
                'priority' => 10,
            ],
            'App\Database\Seeders\SecondSeeder' => [
                'priority' => 50,
                'after' => ['App\Database\Seeders\FirstSeeder'],
            ],
        ]);

        $all = $this->registry->all();

        $this->assertEquals(10, $all['App\Database\Seeders\FirstSeeder']['priority']);
        $this->assertContains(
            'App\Database\Seeders\FirstSeeder',
            $all['App\Database\Seeders\SecondSeeder']['after']
        );
    }

    public function test_remove_seeder(): void
    {
        $this->registry->register('App\Database\Seeders\TestSeeder');
        $this->assertTrue($this->registry->has('App\Database\Seeders\TestSeeder'));

        $this->registry->remove('App\Database\Seeders\TestSeeder');

        $this->assertFalse($this->registry->has('App\Database\Seeders\TestSeeder'));
    }

    public function test_get_ordered_respects_priority(): void
    {
        $this->registry->registerMany([
            'App\Database\Seeders\LowPriority' => 90,
            'App\Database\Seeders\HighPriority' => 10,
            'App\Database\Seeders\MediumPriority' => 50,
        ]);

        $ordered = $this->registry->getOrdered();

        $highIndex = array_search('App\Database\Seeders\HighPriority', $ordered);
        $mediumIndex = array_search('App\Database\Seeders\MediumPriority', $ordered);
        $lowIndex = array_search('App\Database\Seeders\LowPriority', $ordered);

        // Higher priority (lower number) runs first
        $this->assertLessThan($mediumIndex, $highIndex);
        $this->assertLessThan($lowIndex, $mediumIndex);
    }

    public function test_get_ordered_respects_dependencies(): void
    {
        $this->registry->register('App\Database\Seeders\First', priority: 10);
        $this->registry->register(
            'App\Database\Seeders\Second',
            priority: 50,
            after: ['App\Database\Seeders\First']
        );
        $this->registry->register(
            'App\Database\Seeders\Third',
            priority: 90,
            after: ['App\Database\Seeders\Second']
        );

        $ordered = $this->registry->getOrdered();

        $firstIndex = array_search('App\Database\Seeders\First', $ordered);
        $secondIndex = array_search('App\Database\Seeders\Second', $ordered);
        $thirdIndex = array_search('App\Database\Seeders\Third', $ordered);

        $this->assertLessThan($secondIndex, $firstIndex, 'First should come before Second');
        $this->assertLessThan($thirdIndex, $secondIndex, 'Second should come before Third');
    }

    public function test_get_ordered_detects_circular_dependency(): void
    {
        $this->registry->register(
            'App\Database\Seeders\A',
            after: ['App\Database\Seeders\B']
        );
        $this->registry->register(
            'App\Database\Seeders\B',
            after: ['App\Database\Seeders\A']
        );

        $this->expectException(CircularDependencyException::class);

        $this->registry->getOrdered();
    }

    public function test_merge_combines_registries(): void
    {
        $this->registry->register('App\Database\Seeders\First', priority: 10);

        $other = new SeederRegistry;
        $other->register('App\Database\Seeders\Second', priority: 20);

        $this->registry->merge($other);

        $this->assertTrue($this->registry->has('App\Database\Seeders\First'));
        $this->assertTrue($this->registry->has('App\Database\Seeders\Second'));
    }

    public function test_merge_does_not_overwrite_existing(): void
    {
        $this->registry->register('App\Database\Seeders\Same', priority: 10);

        $other = new SeederRegistry;
        $other->register('App\Database\Seeders\Same', priority: 99);

        $this->registry->merge($other);

        $all = $this->registry->all();
        $this->assertEquals(10, $all['App\Database\Seeders\Same']['priority']);
    }

    public function test_clear_removes_all(): void
    {
        $this->registry->registerMany([
            'App\Database\Seeders\First' => 10,
            'App\Database\Seeders\Second' => 20,
        ]);

        $this->assertCount(2, $this->registry->all());

        $this->registry->clear();

        $this->assertEmpty($this->registry->all());
    }

    public function test_fluent_interface(): void
    {
        $result = $this->registry
            ->register('App\Database\Seeders\First', priority: 10)
            ->register('App\Database\Seeders\Second', after: ['App\Database\Seeders\First'])
            ->remove('App\Database\Seeders\First')
            ->register('App\Database\Seeders\NewFirst', priority: 5);

        $this->assertInstanceOf(SeederRegistry::class, $result);
        $this->assertTrue($this->registry->has('App\Database\Seeders\Second'));
        $this->assertTrue($this->registry->has('App\Database\Seeders\NewFirst'));
        $this->assertFalse($this->registry->has('App\Database\Seeders\First'));
    }

    public function test_uses_default_priority(): void
    {
        $this->registry->register('App\Database\Seeders\TestSeeder');

        $all = $this->registry->all();

        $this->assertEquals(
            SeederDiscovery::DEFAULT_PRIORITY,
            $all['App\Database\Seeders\TestSeeder']['priority']
        );
    }
}
