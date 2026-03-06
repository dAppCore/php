<?php

declare(strict_types=1);

namespace Core\Actions;

/**
 * Base Action trait for single-purpose business logic classes.
 *
 * Actions are small, focused classes that do one thing well.
 * They extract complex logic from controllers and Livewire components.
 *
 * Convention:
 * - One action per file
 * - Named after what it does: CreatePage, PublishPost, SendInvoice
 * - Single public method: handle() or __invoke()
 * - Dependencies injected via constructor
 * - Static run() helper for convenience
 *
 * Usage:
 *   // Via dependency injection
 *   public function __construct(private CreatePage $createPage) {}
 *   $page = $this->createPage->handle($user, $data);
 *
 *   // Via static helper
 *   $page = CreatePage::run($user, $data);
 *
 *   // Via app container
 *   $page = app(CreatePage::class)->handle($user, $data);
 *
 * Directory structure:
 *   app/Mod/{Module}/Actions/
 *   ├── CreateThing.php
 *   ├── UpdateThing.php
 *   ├── DeleteThing.php
 *   └── Thing/
 *       ├── PublishThing.php
 *       └── ArchiveThing.php
 */
trait Action
{
    /**
     * Run the action via the container.
     *
     * Resolves the action from the container (with dependencies)
     * and calls handle() with the provided arguments.
     */
    public static function run(mixed ...$args): mixed
    {
        return app(static::class)->handle(...$args);
    }
}
