<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Seo;

use Core\Seo\Validation\SchemaValidator;

/**
 * JSON-LD Schema Generator.
 *
 * High-level schema generation that automatically analyses content
 * to include appropriate schema types (Article, HowTo, FAQ, Breadcrumb).
 *
 * For lower-level building blocks, see Services\SchemaBuilderService.
 */
class Schema
{
    /**
     * Get organisation name from config.
     */
    protected function organisationName(): string
    {
        return config('core.organisation.name', config('core.app.name', 'Core PHP'));
    }

    /**
     * Get organisation URL from config.
     */
    protected function organisationUrl(): string
    {
        return config('core.organisation.url', config('app.url', 'https://core.test'));
    }

    /**
     * Get organisation logo from config.
     */
    protected function organisationLogo(): string
    {
        $logo = config('core.organisation.logo');
        if ($logo) {
            return $logo;
        }

        return $this->organisationUrl().config('core.app.logo', '/images/logo.svg');
    }

    /**
     * Get base domain from config.
     */
    protected function baseDomain(): string
    {
        return config('core.domain.base', 'core.test');
    }

    /**
     * Generate complete JSON-LD schema for a content item.
     *
     * @param  object  $item  Content item model instance (expects ContentItem-like interface)
     */
    public function generateSchema(object $item, array $options = []): array
    {
        $graph = [];

        // Organisation schema (always included)
        $graph[] = $this->organisationSchema();

        // Article schema
        $graph[] = $this->articleSchema($item, $options);

        // Breadcrumb schema
        $graph[] = $this->breadcrumbSchema($item);

        // HowTo schema if article has steps
        if ($this->hasSteps($item)) {
            $graph[] = $this->howToSchema($item);
        }

        // FAQ schema if article has FAQ section
        if ($faq = $this->extractFaq($item)) {
            $graph[] = $this->faqSchema($faq);
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * Generate article schema.
     *
     * @param  object  $item  Content item model instance (expects ContentItem-like interface)
     */
    public function articleSchema(object $item, array $options = []): array
    {
        $type = $options['type'] ?? 'TechArticle';
        $wordCount = str_word_count(strip_tags($item->display_content ?? ''));
        $orgUrl = $this->organisationUrl();
        $orgName = $this->organisationName();

        $schema = [
            '@type' => $type,
            '@id' => $this->getArticleUrl($item).'#article',
            'headline' => $item->title,
            'description' => $item->excerpt ?? $this->generateExcerpt($item),
            'url' => $this->getArticleUrl($item),
            'datePublished' => $item->wp_created_at?->toIso8601String() ?? $item->created_at->toIso8601String(),
            'dateModified' => $item->wp_modified_at?->toIso8601String() ?? $item->updated_at->toIso8601String(),
            'wordCount' => $wordCount,
            'inLanguage' => 'en-GB',
            'isPartOf' => [
                '@type' => 'WebSite',
                '@id' => $orgUrl.'/#website',
                'name' => $orgName,
                'url' => $orgUrl,
            ],
            'publisher' => [
                '@id' => $orgUrl.'/#organization',
            ],
        ];

        // Add author if available
        if ($item->author) {
            $schema['author'] = [
                '@type' => 'Person',
                'name' => $item->author->display_name ?? $item->author->name,
                'url' => $orgUrl.'/team/'.($item->author->slug ?? ''),
            ];
        } else {
            $schema['author'] = [
                '@id' => $orgUrl.'/#organization',
            ];
        }

        // Add featured image if available
        if ($item->featuredMedia) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => $item->featuredMedia->source_url,
                'width' => $item->featuredMedia->width,
                'height' => $item->featuredMedia->height,
            ];
        }

        // Add about (software application) for help articles
        if ($options['service'] ?? null) {
            $schema['about'] = [
                '@type' => 'SoftwareApplication',
                'name' => $options['service'],
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
            ];
        }

        return $schema;
    }

    /**
     * Generate HowTo schema from article content.
     *
     * @param  object  $item  Content item model instance (expects ContentItem-like interface)
     */
    public function howToSchema(object $item): array
    {
        $steps = $this->extractSteps($item);

        $schema = [
            '@type' => 'HowTo',
            '@id' => $this->getArticleUrl($item).'#howto',
            'name' => $item->title,
            'description' => $item->excerpt ?? $this->generateExcerpt($item),
            'totalTime' => 'PT'.($item->seo_meta['reading_time'] ?? 5).'M',
            'step' => [],
        ];

        foreach ($steps as $index => $step) {
            $schema['step'][] = [
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'name' => $step['title'] ?? 'Step '.($index + 1),
                'text' => $step['text'],
                'url' => $this->getArticleUrl($item).'#step-'.($index + 1),
            ];
        }

        return $schema;
    }

    /**
     * Generate FAQ schema.
     */
    public function faqSchema(array $faqs): array
    {
        $schema = [
            '@type' => 'FAQPage',
            'mainEntity' => [],
        ];

        foreach ($faqs as $faq) {
            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }

        return $schema;
    }

    /**
     * Generate breadcrumb schema.
     *
     * @param  object  $item  Content item model instance (expects ContentItem-like interface)
     */
    public function breadcrumbSchema(object $item): array
    {
        $workspace = $item->workspace;
        $domain = $workspace?->domain ?? $this->baseDomain();

        $items = [
            ['name' => 'Home', 'url' => "https://{$domain}/"],
        ];

        // Add category if available
        $category = $item->categories->first();
        if ($category) {
            $items[] = [
                'name' => $category->name,
                'url' => "https://{$domain}/help/{$category->slug}",
            ];
        }

        // Add current page
        $items[] = [
            'name' => $item->title,
            'url' => $this->getArticleUrl($item),
        ];

        $schema = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [],
        ];

        foreach ($items as $index => $breadcrumb) {
            $schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $breadcrumb['name'],
                'item' => $breadcrumb['url'],
            ];
        }

        return $schema;
    }

    /**
     * Generate organisation schema.
     */
    public function organisationSchema(): array
    {
        $orgUrl = $this->organisationUrl();
        $orgName = $this->organisationName();
        $orgLogo = $this->organisationLogo();

        // Collect social links from config
        $sameAs = array_values(array_filter([
            config('core.social.twitter'),
            config('core.social.linkedin'),
            config('core.social.facebook'),
            config('core.social.instagram'),
            config('core.social.github'),
            config('core.social.youtube'),
        ]));

        $schema = [
            '@type' => 'Organization',
            '@id' => $orgUrl.'/#organization',
            'name' => $orgName,
            'url' => $orgUrl,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $orgLogo,
            ],
        ];

        if (! empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }

        return $schema;
    }

    /**
     * Check if article content contains numbered steps.
     *
     * @param  object  $item  Content item model instance
     */
    protected function hasSteps(object $item): bool
    {
        $content = $item->display_content ?? '';

        // Look for numbered lists or step patterns
        return preg_match('/(?:^|\n)\s*(?:\d+\.|Step \d+)/m', $content) === 1;
    }

    /**
     * Extract steps from article content.
     *
     * @param  object  $item  Content item model instance
     */
    protected function extractSteps(object $item): array
    {
        $content = $item->display_content ?? '';
        $steps = [];

        // Try to extract from JSON content if available
        if ($item->content_json && isset($item->content_json['blocks'])) {
            foreach ($item->content_json['blocks'] as $block) {
                if ($block['type'] === 'list' && ($block['ordered'] ?? false)) {
                    foreach ($block['items'] as $listItem) {
                        $steps[] = [
                            'text' => $listItem['content'] ?? $listItem,
                        ];
                    }
                }
            }
        }

        // Fallback: extract from HTML/text
        if (empty($steps)) {
            preg_match_all('/(?:^|\n)\s*(\d+)\.\s*(.+?)(?=\n\s*\d+\.|\n\n|$)/s', $content, $matches);
            foreach ($matches[2] as $stepText) {
                $steps[] = [
                    'text' => trim($stepText),
                ];
            }
        }

        return $steps;
    }

    /**
     * Extract FAQ from article content.
     *
     * @param  object  $item  Content item model instance
     */
    protected function extractFaq(object $item): ?array
    {
        $content = $item->display_content ?? '';
        $faqs = [];

        // Look for FAQ section
        if (preg_match('/(?:## FAQ|## Frequently Asked|### FAQ)(.*?)(?=\n## |\n---|\Z)/si', $content, $faqSection)) {
            // Extract Q&A pairs
            preg_match_all('/(?:\*\*|###?\s*)(.+?)\??(?:\*\*|)\s*\n+(.+?)(?=\n(?:\*\*|###?\s*)|\n\n\n|\Z)/s', $faqSection[1], $matches);

            foreach ($matches[1] as $index => $question) {
                $answer = trim($matches[2][$index] ?? '');
                if ($answer) {
                    $faqs[] = [
                        'question' => trim($question, " \t\n\r\0\x0B*?"),
                        'answer' => $answer,
                    ];
                }
            }
        }

        return empty($faqs) ? null : $faqs;
    }

    /**
     * Get the full URL for an article.
     *
     * @param  object  $item  Content item model instance
     */
    protected function getArticleUrl(object $item): string
    {
        $workspace = $item->workspace;
        $domain = $workspace?->domain ?? $this->baseDomain();

        if ($item->type === 'post') {
            return "https://{$domain}/blog/{$item->slug}";
        }

        return "https://{$domain}/{$item->slug}";
    }

    /**
     * Generate an excerpt from content.
     *
     * @param  object  $item  Content item model instance
     */
    protected function generateExcerpt(object $item, int $length = 155): string
    {
        $content = strip_tags($item->display_content ?? '');
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        if (strlen($content) <= $length) {
            return $content;
        }

        return substr($content, 0, $length - 3).'...';
    }

    /**
     * Render schema as JSON-LD script tag.
     *
     * Uses JSON_HEX_TAG to prevent XSS via </script> in content.
     */
    public function toScriptTag(array $schema): string
    {
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG);

        return '<script type="application/ld+json">'.$json.'</script>';
    }

    /**
     * Validate schema against schema.org specifications.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public function validate(array $schema): array
    {
        return SchemaValidator::validate($schema);
    }

    /**
     * Generate schema with validation.
     *
     * @param  object  $item  Content item model instance (expects ContentItem-like interface)
     *
     * @throws \InvalidArgumentException if schema validation fails
     */
    public function generateValidatedSchema(object $item, array $options = []): array
    {
        $schema = $this->generateSchema($item, $options);
        $result = $this->validate($schema);

        if (! $result['valid']) {
            throw new \InvalidArgumentException(
                'Schema validation failed: '.implode(', ', $result['errors'])
            );
        }

        return $schema;
    }
}
