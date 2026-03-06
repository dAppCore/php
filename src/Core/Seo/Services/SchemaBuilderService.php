<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo\Services;

use Core\Seo\Validation\SchemaValidator;

/**
 * Schema.org structured data builder.
 *
 * Generates JSON-LD structured data for various schema types:
 * - Article/BlogPosting
 * - HowTo
 * - FAQ
 * - BreadcrumbList
 * - Organization
 * - WebSite
 * - SoftwareApplication
 * - LocalBusiness
 */
class SchemaBuilderService
{
    /**
     * Build Article schema for a content item.
     *
     * @param  object  $item  Content item model instance (expects ContentItem-like interface)
     */
    public function buildArticleSchema(object $item): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $item->title,
            'description' => $item->excerpt,
            'author' => $item->author ? [
                '@type' => 'Person',
                'name' => $item->author->name,
            ] : null,
            'datePublished' => $item->wp_created_at?->toIso8601String(),
            'dateModified' => $item->wp_modified_at?->toIso8601String(),
            'publisher' => $this->getOrganizationSchema($item->workspace),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $this->getContentUrl($item),
            ],
        ];
    }

    /**
     * Build BlogPosting schema (more specific than Article).
     *
     * @param  object  $item  Content item model instance (expects ContentItem-like interface)
     */
    public function buildBlogPostingSchema(object $item): array
    {
        $schema = $this->buildArticleSchema($item);
        $schema['@type'] = 'BlogPosting';

        if ($item->featuredMedia) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => $item->featuredMedia->source_url,
            ];
        }

        return $schema;
    }

    /**
     * Build HowTo schema for instructional content.
     *
     * @param  object  $item  Content item model instance (expects ContentItem-like interface)
     */
    public function buildHowToSchema(object $item, array $steps): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $item->title,
            'description' => $item->excerpt,
            'step' => array_map(fn ($step, $i) => [
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => $step['title'] ?? 'Step '.($i + 1),
                'text' => $step['content'] ?? $step['text'] ?? '',
                'url' => $this->getContentUrl($item).'#step-'.($i + 1),
            ], $steps, array_keys($steps)),
            'totalTime' => $this->calculateTotalTime($steps),
        ];
    }

    /**
     * Build FAQ schema.
     */
    public function buildFAQSchema(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faqs),
        ];
    }

    /**
     * Build BreadcrumbList schema.
     */
    public function buildBreadcrumbSchema(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'] ?? null,
            ], $items, array_keys($items)),
        ];
    }

    /**
     * Build Organization schema.
     *
     * @param  object|null  $workspace  Workspace model instance (expects name and domain properties)
     */
    public function getOrganizationSchema(?object $workspace = null): array
    {
        return [
            '@type' => 'Organization',
            'name' => $workspace?->name ?? 'Host UK',
            'url' => $workspace !== null ? "https://{$workspace->domain}" : 'https://host.uk.com',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => 'https://host.uk.com/images/logo.png',
            ],
        ];
    }

    /**
     * Build WebSite schema.
     *
     * @param  object  $workspace  Workspace model instance (expects name and domain properties)
     */
    public function buildWebsiteSchema(object $workspace): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $workspace->name,
            'url' => "https://{$workspace->domain}",
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => "https://{$workspace->domain}/search?q={search_term_string}",
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Build SoftwareApplication schema for tools/apps.
     */
    public function buildSoftwareApplicationSchema(array $data): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'applicationCategory' => $data['category'] ?? 'WebApplication',
            'operatingSystem' => $data['os'] ?? 'Web',
            'offers' => isset($data['price']) ? [
                '@type' => 'Offer',
                'price' => $data['price'],
                'priceCurrency' => $data['currency'] ?? 'GBP',
            ] : null,
            'aggregateRating' => isset($data['rating']) ? [
                '@type' => 'AggregateRating',
                'ratingValue' => $data['rating'],
                'ratingCount' => $data['review_count'] ?? 1,
            ] : null,
        ];
    }

    /**
     * Build LocalBusiness schema.
     */
    public function buildLocalBusinessSchema(array $data): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => $data['type'] ?? 'LocalBusiness',
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'url' => $data['url'] ?? null,
            'telephone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => isset($data['address']) ? [
                '@type' => 'PostalAddress',
                'streetAddress' => $data['address']['street'] ?? null,
                'addressLocality' => $data['address']['city'] ?? null,
                'addressRegion' => $data['address']['region'] ?? null,
                'postalCode' => $data['address']['postcode'] ?? null,
                'addressCountry' => $data['address']['country'] ?? 'GB',
            ] : null,
        ];
    }

    /**
     * Combine multiple schemas into a graph.
     */
    public function combineSchemas(array $schemas): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_map(function ($schema) {
                unset($schema['@context']);

                return $schema;
            }, $schemas),
        ];
    }

    /**
     * Generate JSON-LD script tag.
     *
     * Uses JSON_HEX_TAG to prevent XSS via </script> in content.
     */
    public function toScriptTag(array $schema): string
    {
        return '<script type="application/ld+json">'.
            json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG).
            '</script>';
    }

    /**
     * Get the canonical URL for a content item.
     *
     * @param  object  $item  Content item model instance
     */
    private function getContentUrl(object $item): string
    {
        $domain = $item->workspace?->domain ?? 'host.uk.com';

        if ($item->type === 'post') {
            return "https://{$domain}/blog/{$item->slug}";
        }

        return "https://{$domain}/{$item->slug}";
    }

    /**
     * Calculate total time for HowTo steps (placeholder).
     */
    private function calculateTotalTime(array $steps): ?string
    {
        // Estimate 2 minutes per step
        $minutes = count($steps) * 2;

        return "PT{$minutes}M";
    }

    /**
     * Validate schema against schema.org specifications.
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validate(array $schema): array
    {
        return SchemaValidator::validate($schema);
    }
}
