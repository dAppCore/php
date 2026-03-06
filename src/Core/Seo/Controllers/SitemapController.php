<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Controllers;

use Core\Front\Controller;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Sitemap index pointing to child sitemaps.
     */
    public function index(): Response
    {
        $sitemaps = [
            ['loc' => url('/sitemap-pages.xml')],
            ['loc' => 'https://social.host.uk.com/sitemap.xml'],
            ['loc' => 'https://lt.hn/sitemap.xml'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($sitemaps as $sitemap) {
            $xml .= '<sitemap>';
            $xml .= '<loc>'.htmlspecialchars($sitemap['loc']).'</loc>';
            $xml .= '<lastmod>'.now()->toW3cString().'</lastmod>';
            $xml .= '</sitemap>';
        }

        $xml .= '</sitemapindex>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Marketing pages sitemap (host.uk.com static pages).
     */
    public function pages(): Response
    {
        $urls = $this->getAllUrls();

        return $this->xmlResponse($urls);
    }

    /**
     * Plain text sitemap (one URL per line).
     */
    public function text(): Response
    {
        $urls = $this->getAllUrls();
        $lines = array_map(fn ($url) => $url['loc'], $urls);

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Generate XML sitemap response.
     */
    protected function xmlResponse(array $urls): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>'.htmlspecialchars($url['loc']).'</loc>';
            $xml .= '<lastmod>'.now()->toW3cString().'</lastmod>';
            $xml .= '<changefreq>'.($url['changefreq'] ?? 'monthly').'</changefreq>';
            $xml .= '<priority>'.($url['priority'] ?? '0.5').'</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Get all URLs for the sitemap.
     */
    protected function getAllUrls(): array
    {
        return array_merge($this->getStaticUrls(), $this->getOssProjectUrls());
    }

    /**
     * Get all static public URLs for the sitemap.
     */
    protected function getStaticUrls(): array
    {
        return [
            // Homepage
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],

            // Core pages
            ['loc' => url('/pricing'), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => url('/about'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/partner'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/faq'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/contact'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/waitlist'), 'priority' => '0.6', 'changefreq' => 'monthly'],

            // Services
            ['loc' => url('/services'), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => url('/services/bio'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('/services/social'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('/services/analytics'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('/services/trust'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('/services/notify'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('/services/mail'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('/services/seo'), 'priority' => '0.8', 'changefreq' => 'monthly'],

            // AI section
            ['loc' => url('/ai'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => url('/ai/mcp'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/ai/ethics'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/ai/for-agents'), 'priority' => '0.7', 'changefreq' => 'weekly'],

            // For (audience landing pages)
            ['loc' => url('/for'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/for/content-creators'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/for/fansites'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/for/of-agencies'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/for/social-media'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/for/streamers'), 'priority' => '0.7', 'changefreq' => 'monthly'],

            // Developers
            ['loc' => url('/developers/mcp'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/developers/mcp/social'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/developers/mcp/bio'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/developers/mcp/marketing-agent'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/developers/mcp/gemini'), 'priority' => '0.6', 'changefreq' => 'monthly'],

            // Open Source
            ['loc' => url('/oss'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/oss/rfc'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/dapp-fm'), 'priority' => '0.6', 'changefreq' => 'monthly'],

            // API Documentation
            ['loc' => url('/guides'), 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => url('/guides/quickstart'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/guides/authentication'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/guides/pages'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/guides/qrcodes'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/guides/errors'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/guides/webhooks'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => url('/reference'), 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => url('/swagger'), 'priority' => '0.5', 'changefreq' => 'weekly'],
            ['loc' => url('/scalar'), 'priority' => '0.5', 'changefreq' => 'weekly'],
            ['loc' => url('/redoc'), 'priority' => '0.5', 'changefreq' => 'weekly'],

            // Legal
            ['loc' => url('/privacy'), 'priority' => '0.5', 'changefreq' => 'yearly'],
            ['loc' => url('/terms'), 'priority' => '0.5', 'changefreq' => 'yearly'],
        ];
    }

    /**
     * Get URLs for OSS project pages.
     */
    protected function getOssProjectUrls(): array
    {
        $slugs = [
            'trees-for-agents',
            'btcpayserver-docker',
            'ansible-deployment',
            'ansible-cloudns',
            'enchantrix',
            'poindexter',
            'borg',
            'core',
            'build',
            'mining',
            'updater',
            'help',
            'axioms-of-conscious-systems',
            'lthn',
            'blockchain',
            'server',
        ];

        return array_map(fn ($slug) => [
            'loc' => url('/oss/'.$slug),
            'priority' => '0.5',
            'changefreq' => 'monthly',
        ], $slugs);
    }
}
