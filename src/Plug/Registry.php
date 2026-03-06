<?php

declare(strict_types=1);

namespace Core\Plug;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Provider registry with auto-discovery.
 *
 * Discovers providers from directory structure and provides
 * capability checking and class resolution.
 */
class Registry
{
    private array $providers = [];

    private bool $discovered = false;

    /**
     * Categories in the Plug directory.
     */
    private const CATEGORIES = ['Social', 'Web3', 'Content', 'Chat', 'Business'];

    /**
     * Discover all providers from directory structure.
     */
    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $basePath = __DIR__;

        foreach (self::CATEGORIES as $category) {
            $categoryPath = $basePath.'/'.$category;

            if (! is_dir($categoryPath)) {
                continue;
            }

            foreach (scandir($categoryPath) as $provider) {
                if ($provider === '.' || $provider === '..') {
                    continue;
                }

                $providerPath = $categoryPath.'/'.$provider;

                if (! is_dir($providerPath)) {
                    continue;
                }

                $identifier = Str::lower($provider);

                $this->providers[$identifier] = [
                    'category' => $category,
                    'name' => $provider,
                    'namespace' => "Plug\\{$category}\\{$provider}",
                    'path' => $providerPath,
                ];
            }
        }

        $this->discovered = true;
    }

    /**
     * Get all registered provider identifiers.
     *
     * @return string[]
     */
    public function identifiers(): array
    {
        $this->discover();

        return array_keys($this->providers);
    }

    /**
     * Check if a provider exists.
     */
    public function has(string $identifier): bool
    {
        $this->discover();

        return isset($this->providers[$identifier]);
    }

    /**
     * Get provider metadata.
     */
    public function get(string $identifier): ?array
    {
        $this->discover();

        return $this->providers[$identifier] ?? null;
    }

    /**
     * Check if provider supports an operation.
     */
    public function supports(string $identifier, string $operation): bool
    {
        $meta = $this->get($identifier);

        if (! $meta) {
            return false;
        }

        $className = $meta['namespace'].'\\'.ucfirst($operation);

        return class_exists($className);
    }

    /**
     * Get an operation class for a provider.
     *
     * @return class-string|null
     */
    public function operation(string $identifier, string $operation): ?string
    {
        if (! $this->supports($identifier, $operation)) {
            return null;
        }

        $meta = $this->get($identifier);

        return $meta['namespace'].'\\'.ucfirst($operation);
    }

    /**
     * Get all providers.
     */
    public function all(): Collection
    {
        $this->discover();

        return collect($this->providers);
    }

    /**
     * Get provider identifiers by category.
     */
    public function byCategory(string $category): Collection
    {
        $this->discover();

        return collect($this->providers)
            ->filter(fn ($meta) => $meta['category'] === $category)
            ->keys();
    }

    /**
     * Get providers that support a specific operation.
     */
    public function withCapability(string $operation): Collection
    {
        $this->discover();

        return collect($this->providers)->filter(
            fn ($meta, $id) => $this->supports($id, $operation)
        );
    }

    /**
     * Get available categories.
     *
     * @return string[]
     */
    public function categories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Get the display name for a provider.
     */
    public function displayName(string $identifier): ?string
    {
        $authClass = $this->operation($identifier, 'auth');

        if ($authClass && method_exists($authClass, 'name')) {
            return $authClass::name();
        }

        $meta = $this->get($identifier);

        return $meta['name'] ?? null;
    }
}
