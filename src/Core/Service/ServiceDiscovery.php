<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Service;

use Core\Service\Contracts\ServiceDefinition;
use Core\Service\Contracts\ServiceDependency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Discovers and manages service definitions across the codebase.
 *
 * Scans configured module paths for classes implementing ServiceDefinition,
 * validates dependencies between services, and provides resolution order
 * for service initialization.
 *
 * ## Service Lifecycle Overview
 *
 * The Core PHP framework manages services through a well-defined lifecycle:
 *
 * ```
 * ┌─────────────────────────────────────────────────────────────────┐
 * │                    SERVICE LIFECYCLE                            │
 * ├─────────────────────────────────────────────────────────────────┤
 * │                                                                 │
 * │  1. DISCOVERY PHASE                                             │
 * │     ├── Scan module paths for ServiceDefinition implementations │
 * │     ├── Parse definition() arrays to extract service metadata   │
 * │     └── Build service registry indexed by service code          │
 * │                                                                 │
 * │  2. DEPENDENCY RESOLUTION PHASE                                 │
 * │     ├── Collect dependencies() from each service                │
 * │     ├── Validate required dependencies are available            │
 * │     ├── Check version constraints are satisfied                 │
 * │     └── Detect and prevent circular dependencies                │
 * │                                                                 │
 * │  3. INITIALIZATION PHASE (dependency order)                     │
 * │     ├── Services initialized in topologically sorted order      │
 * │     ├── Dependencies always initialized before dependents       │
 * │     └── Service instances registered in container               │
 * │                                                                 │
 * │  4. RUNTIME PHASE                                               │
 * │     ├── Services respond to health checks (HealthCheckable)     │
 * │     ├── Version() provides compatibility information            │
 * │     └── Admin menu items rendered (AdminMenuProvider)           │
 * │                                                                 │
 * │  5. DEPRECATION/SUNSET PHASE                                    │
 * │     ├── Deprecated services log warnings on access              │
 * │     ├── Services past sunset date may throw exceptions          │
 * │     └── Migration to replacement services should occur          │
 * │                                                                 │
 * └─────────────────────────────────────────────────────────────────┘
 * ```
 *
 * ## Creating a New Service
 *
 * 1. **Implement ServiceDefinition**: Create a class implementing the interface
 * 2. **Define Metadata**: Provide service code, name, description via `definition()`
 * 3. **Declare Dependencies**: List required/optional services via `dependencies()`
 * 4. **Set Version**: Specify the service contract version via `version()`
 * 5. **Add Health Checks**: Implement `HealthCheckable` for monitoring
 *
 * ```php
 * use Core\Service\Contracts\ServiceDefinition;
 * use Core\Service\Contracts\HealthCheckable;
 * use Core\Service\Concerns\HasServiceVersion;
 *
 * class BillingService implements ServiceDefinition, HealthCheckable
 * {
 *     use HasServiceVersion;
 *
 *     public static function definition(): array
 *     {
 *         return [
 *             'code' => 'billing',
 *             'module' => 'Mod\\Billing',
 *             'name' => 'Billing Service',
 *             'tagline' => 'Handle payments and subscriptions',
 *             'icon' => 'credit-card',
 *             'color' => '#10B981',
 *         ];
 *     }
 *
 *     public static function dependencies(): array
 *     {
 *         return [
 *             ServiceDependency::required('auth', '>=1.0.0'),
 *             ServiceDependency::optional('analytics'),
 *         ];
 *     }
 *
 *     public function healthCheck(): HealthCheckResult
 *     {
 *         // Check payment provider connectivity
 *         return HealthCheckResult::healthy();
 *     }
 * }
 * ```
 *
 * ## Discovery Process
 *
 * 1. Scans module paths for classes implementing ServiceDefinition
 * 2. Validates each service's dependencies are available
 * 3. Detects circular dependencies
 * 4. Returns services in dependency-resolved order
 *
 * ## Caching
 *
 * Discovery results are cached for 1 hour (configurable). Clear the cache when
 * adding new services or modifying dependencies:
 *
 * ```php
 * $discovery->clearCache();
 * ```
 *
 * Caching can be disabled via config: `core.services.cache_discovery => false`
 *
 * ## Example Usage
 *
 * ```php
 * $discovery = app(ServiceDiscovery::class);
 *
 * // Get all registered services
 * $services = $discovery->discover();
 *
 * // Get services in dependency order
 * $ordered = $discovery->getResolutionOrder();
 *
 * // Check if a service is available
 * $available = $discovery->has('billing');
 *
 * // Get a specific service definition class
 * $billingClass = $discovery->get('billing');
 *
 * // Get instantiated service
 * $billing = $discovery->getInstance('billing');
 *
 * // Validate all dependencies are satisfied
 * $missing = $discovery->validateDependencies();
 * if (!empty($missing)) {
 *     foreach ($missing as $service => $deps) {
 *         logger()->error("Service {$service} missing: " . implode(', ', $deps));
 *     }
 * }
 * ```
 *
 *
 * @see ServiceDefinition For the service contract interface
 * @see ServiceVersion For versioning and deprecation
 * @see ServiceDependency For declaring dependencies
 * @see HealthCheckable For service health monitoring
 */
class ServiceDiscovery
{
    /**
     * Cache key for discovered services.
     */
    protected const CACHE_KEY = 'core.services.discovered';

    /**
     * Cache TTL in seconds (1 hour by default).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Discovered service definitions indexed by service code.
     *
     * @var array<string, class-string<ServiceDefinition>>
     */
    protected array $services = [];

    /**
     * Whether discovery has been performed.
     */
    protected bool $discovered = false;

    /**
     * Additional paths to scan for services.
     *
     * @var array<string>
     */
    protected array $additionalPaths = [];

    /**
     * Manually registered service classes.
     *
     * @var array<class-string<ServiceDefinition>>
     */
    protected array $registered = [];

    /**
     * Add a path to scan for service definitions.
     */
    public function addPath(string $path): self
    {
        $this->additionalPaths[] = $path;
        $this->discovered = false;

        return $this;
    }

    /**
     * Manually register a service definition class.
     *
     * Validates the service definition before registering. The validation checks:
     * - Class exists and implements ServiceDefinition
     * - Definition array contains required 'code' field
     * - Definition array contains required 'module' field
     * - Definition array contains required 'name' field
     * - Code is a non-empty string with valid format
     *
     * @param  class-string<ServiceDefinition>  $serviceClass
     * @param  bool  $validate  Whether to validate the service definition (default: true)
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function register(string $serviceClass, bool $validate = true): self
    {
        if ($validate) {
            $this->validateServiceClass($serviceClass);
        }

        $this->registered[] = $serviceClass;
        $this->discovered = false;

        return $this;
    }

    /**
     * Validate a service definition class.
     *
     * @param  class-string<ServiceDefinition>  $serviceClass
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function validateServiceClass(string $serviceClass): void
    {
        // Check class exists
        if (! class_exists($serviceClass)) {
            throw new \InvalidArgumentException(
                "Service class '{$serviceClass}' does not exist"
            );
        }

        // Check implements interface
        if (! is_subclass_of($serviceClass, ServiceDefinition::class)) {
            throw new \InvalidArgumentException(
                "Service class '{$serviceClass}' must implement ".ServiceDefinition::class
            );
        }

        // Validate definition array
        try {
            $definition = $serviceClass::definition();
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                "Service class '{$serviceClass}' definition() method threw an exception: ".$e->getMessage()
            );
        }

        $this->validateDefinitionArray($serviceClass, $definition);
    }

    /**
     * Validate a service definition array.
     *
     *
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validateDefinitionArray(string $serviceClass, array $definition): void
    {
        $requiredFields = ['code', 'module', 'name'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (! isset($definition[$field]) || $definition[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            throw new \InvalidArgumentException(
                "Service '{$serviceClass}' definition() is missing required fields: ".implode(', ', $missingFields)
            );
        }

        // Validate code format (should be a simple identifier)
        if (! is_string($definition['code'])) {
            throw new \InvalidArgumentException(
                "Service '{$serviceClass}' code must be a string"
            );
        }

        if (! preg_match('/^[a-z][a-z0-9_-]*$/i', $definition['code'])) {
            throw new \InvalidArgumentException(
                "Service '{$serviceClass}' code '{$definition['code']}' is invalid. ".
                'Code must start with a letter and contain only letters, numbers, underscores, and hyphens.'
            );
        }

        // Validate module namespace format
        if (! is_string($definition['module'])) {
            throw new \InvalidArgumentException(
                "Service '{$serviceClass}' module must be a string"
            );
        }

        // Validate optional fields if present
        if (isset($definition['sort_order']) && ! is_int($definition['sort_order'])) {
            throw new \InvalidArgumentException(
                "Service '{$serviceClass}' sort_order must be an integer"
            );
        }

        if (isset($definition['color']) && ! $this->isValidColor($definition['color'])) {
            throw new \InvalidArgumentException(
                "Service '{$serviceClass}' color '{$definition['color']}' is invalid. ".
                'Color must be a valid hex color (e.g., #3B82F6)'
            );
        }
    }

    /**
     * Check if a color string is a valid hex color.
     */
    protected function isValidColor(mixed $color): bool
    {
        if (! is_string($color)) {
            return false;
        }

        return (bool) preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color);
    }

    /**
     * Validate all registered services and return validation errors.
     *
     * @return array<string, array<string>> Array of service code => validation errors
     */
    public function validateAll(): array
    {
        $this->discover();
        $errors = [];

        foreach ($this->services as $code => $class) {
            try {
                $this->validateServiceClass($class);
            } catch (\InvalidArgumentException $e) {
                $errors[$code][] = $e->getMessage();
            }

            // Also validate dependencies
            $depErrors = $this->validateServiceDependenciesForService($code, $class);
            if (! empty($depErrors)) {
                $errors[$code] = array_merge($errors[$code] ?? [], $depErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate dependencies for a specific service.
     *
     * @param  class-string<ServiceDefinition>  $class
     * @return array<string>
     */
    protected function validateServiceDependenciesForService(string $code, string $class): array
    {
        $errors = [];
        $dependencies = $this->getServiceDependencies($class);

        foreach ($dependencies as $dependency) {
            if ($dependency->required && ! $this->has($dependency->serviceCode)) {
                $errors[] = "Required dependency '{$dependency->serviceCode}' is not available";
            }

            // Check version constraint
            if ($this->has($dependency->serviceCode)) {
                $depClass = $this->get($dependency->serviceCode);
                if ($depClass !== null) {
                    $version = $depClass::version()->toString();
                    if (! $dependency->satisfiedBy($version)) {
                        $errors[] = sprintf(
                            "Dependency '%s' version mismatch: requires %s, found %s",
                            $dependency->serviceCode,
                            $dependency->getConstraintDescription(),
                            $version
                        );
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Discover all service definitions.
     *
     * @return Collection<string, class-string<ServiceDefinition>>
     */
    public function discover(): Collection
    {
        if ($this->discovered) {
            return collect($this->services);
        }

        // Try to load from cache
        $cached = $this->loadFromCache();
        if ($cached !== null) {
            $this->services = $cached;
            $this->discovered = true;

            return collect($this->services);
        }

        // Perform discovery
        $this->services = $this->performDiscovery();
        $this->discovered = true;

        // Cache results
        $this->saveToCache($this->services);

        return collect($this->services);
    }

    /**
     * Check if a service is available.
     */
    public function has(string $serviceCode): bool
    {
        $this->discover();

        return isset($this->services[$serviceCode]);
    }

    /**
     * Get a service definition class by code.
     *
     * @return class-string<ServiceDefinition>|null
     */
    public function get(string $serviceCode): ?string
    {
        $this->discover();

        return $this->services[$serviceCode] ?? null;
    }

    /**
     * Get service instance by code.
     */
    public function getInstance(string $serviceCode): ?ServiceDefinition
    {
        $class = $this->get($serviceCode);
        if ($class === null) {
            return null;
        }

        return app($class);
    }

    /**
     * Get services in dependency resolution order.
     *
     * Services are ordered so that dependencies come before dependents.
     *
     * @return Collection<int, class-string<ServiceDefinition>>
     *
     * @throws ServiceDependencyException If circular dependency detected
     */
    public function getResolutionOrder(): Collection
    {
        $this->discover();

        $resolved = [];
        $resolving = [];

        foreach ($this->services as $code => $class) {
            $this->resolveService($code, $resolved, $resolving);
        }

        return collect($resolved);
    }

    /**
     * Validate all service dependencies.
     *
     * @return array<string, array<string>> Array of service code => missing dependencies
     */
    public function validateDependencies(): array
    {
        $this->discover();
        $missing = [];

        foreach ($this->services as $code => $class) {
            $dependencies = $this->getServiceDependencies($class);

            foreach ($dependencies as $dependency) {
                if (! $dependency->required) {
                    continue;
                }

                if (! $this->has($dependency->serviceCode)) {
                    $missing[$code][] = $dependency->serviceCode;

                    continue;
                }

                // Check version constraint
                $depClass = $this->get($dependency->serviceCode);
                if ($depClass !== null) {
                    $version = $depClass::version()->toString();
                    if (! $dependency->satisfiedBy($version)) {
                        $missing[$code][] = sprintf(
                            '%s (%s required, %s available)',
                            $dependency->serviceCode,
                            $dependency->getConstraintDescription(),
                            $version
                        );
                    }
                }
            }
        }

        return $missing;
    }

    /**
     * Clear the discovery cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->discovered = false;
        $this->services = [];
    }

    /**
     * Perform the actual discovery process.
     *
     * @return array<string, class-string<ServiceDefinition>>
     */
    protected function performDiscovery(): array
    {
        $services = [];

        // Scan configured module paths
        $paths = array_merge(
            config('core.module_paths', []),
            $this->additionalPaths
        );

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $this->scanPath($path, $services);
        }

        // Add manually registered services
        foreach ($this->registered as $class) {
            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, ServiceDefinition::class)) {
                continue;
            }

            $definition = $class::definition();
            $code = $definition['code'] ?? null;

            if ($code !== null) {
                $services[$code] = $class;
            }
        }

        return $services;
    }

    /**
     * Scan a path for service definitions.
     *
     * @param  array<string, class-string<ServiceDefinition>>  $services
     */
    protected function scanPath(string $path, array &$services): void
    {
        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Skip test files
            if (str_contains($file->getPathname(), '/Tests/')) {
                continue;
            }

            // Skip vendor directories
            if (str_contains($file->getPathname(), '/vendor/')) {
                continue;
            }

            $class = $this->getClassFromFile($file->getPathname());
            if ($class === null) {
                continue;
            }

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, ServiceDefinition::class)) {
                continue;
            }

            try {
                $definition = $class::definition();
                $code = $definition['code'] ?? null;

                if ($code !== null) {
                    $services[$code] = $class;
                }
            } catch (\Throwable) {
                // Skip services that throw during definition()
            }
        }
    }

    /**
     * Extract class name from a PHP file.
     */
    protected function getClassFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($namespace !== null && $class !== null) {
            return $namespace.'\\'.$class;
        }

        return null;
    }

    /**
     * Get dependencies for a service class.
     *
     * @param  class-string<ServiceDefinition>  $class
     * @return array<ServiceDependency>
     */
    protected function getServiceDependencies(string $class): array
    {
        if (! method_exists($class, 'dependencies')) {
            return [];
        }

        return $class::dependencies();
    }

    /**
     * Resolve a service and its dependencies.
     *
     * @param  array<class-string<ServiceDefinition>>  $resolved
     * @param  array<string>  $resolving
     *
     * @throws ServiceDependencyException If circular dependency detected
     */
    protected function resolveService(string $code, array &$resolved, array &$resolving): void
    {
        if (in_array($code, $resolving)) {
            throw new ServiceDependencyException(
                'Circular dependency detected: '.implode(' -> ', [...$resolving, $code])
            );
        }

        $class = $this->services[$code] ?? null;
        if ($class === null || in_array($class, $resolved)) {
            return;
        }

        $resolving[] = $code;

        foreach ($this->getServiceDependencies($class) as $dependency) {
            if ($this->has($dependency->serviceCode)) {
                $this->resolveService($dependency->serviceCode, $resolved, $resolving);
            }
        }

        array_pop($resolving);
        $resolved[] = $class;
    }

    /**
     * Load discovered services from cache.
     *
     * @return array<string, class-string<ServiceDefinition>>|null
     */
    protected function loadFromCache(): ?array
    {
        if (! config('core.services.cache_discovery', true)) {
            return null;
        }

        return Cache::get(self::CACHE_KEY);
    }

    /**
     * Save discovered services to cache.
     *
     * @param  array<string, class-string<ServiceDefinition>>  $services
     */
    protected function saveToCache(array $services): void
    {
        if (! config('core.services.cache_discovery', true)) {
            return;
        }

        Cache::put(self::CACHE_KEY, $services, self::CACHE_TTL);
    }
}
