<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Database\Seeders;

use Core\Bouncer\RedirectService;
use Illuminate\Database\Seeder;

/**
 * Seed 301 redirects for renamed website URLs.
 *
 * URL simplification: removed "host" suffix from service pages.
 * Added 2026-01-16.
 */
class WebsiteRedirectSeeder extends Seeder
{
    /**
     * URL redirects: old path => new path.
     */
    protected array $redirects = [
        // Service pages
        '/services/biohost' => '/services/bio',
        '/services/socialhost' => '/services/social',
        '/services/trusthost' => '/services/trust',
        '/services/mailhost' => '/services/mail',
        '/services/analyticshost' => '/services/analytics',
        '/services/notifyhost' => '/services/notify',

        // MCP documentation
        '/developers/mcp/biohost' => '/developers/mcp/bio',
        '/developers/mcp/socialhost' => '/developers/mcp/social',
    ];

    public function __construct(
        protected RedirectService $service,
    ) {}

    public function run(): void
    {
        foreach ($this->redirects as $from => $to) {
            $this->service->add($from, $to, 301);
        }

        $this->command?->info('Seeded '.count($this->redirects).' website redirects.');
    }
}
