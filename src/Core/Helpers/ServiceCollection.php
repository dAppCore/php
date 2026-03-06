<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use Illuminate\Support\Arr;

/**
 * Collection class for managing service providers (social networks, AI services, media services).
 *
 * This collection provides methods to filter, group, and retrieve information about
 * registered service providers in the SocialHost system.
 */
class ServiceCollection
{
    /**
     * Create a new service collection instance.
     *
     * @param  array<int, class-string>  $services  Array of service class names
     */
    public function __construct(
        public readonly array $services
    ) {}

    /**
     * Filter services by group (social, AI, media, miscellaneous).
     *
     * Requires Core\Mod\Social module to be installed for ServiceGroup enum.
     *
     * @param  object|array<int, object>|null  $group  Service group(s) to filter by (ServiceGroup enum)
     * @return static New collection containing only services in the specified group(s)
     */
    public function group(object|array|null $group = null): static
    {
        return new static(
            array_values(
                array_filter($this->services, function ($serviceClass) use ($group) {
                    return in_array($serviceClass::group(), Arr::wrap($group));
                })
            )
        );
    }

    /**
     * Get the array of service class names.
     *
     * @return array<int, class-string>
     */
    public function getClasses(): array
    {
        return $this->services;
    }

    /**
     * Get the array of service names.
     *
     * @return array<int, string>
     */
    public function getNames(): array
    {
        return array_map(fn ($service) => $service::name(), $this->services);
    }

    /**
     * Get the collection as an array of service metadata.
     *
     * Returns an array where each element contains:
     * - name: The service name (e.g., 'facebook', 'twitter')
     * - group: The service group enum (social, AI, media, miscellaneous)
     * - form: The form configuration array for the service
     *
     * @return array<int, array{name: string, group: object, form: array}>
     */
    public function getCollection(): array
    {
        return array_map(function ($serviceClass) {
            return [
                'name' => $serviceClass::name(),
                'group' => $serviceClass::group(),
                'form' => $serviceClass::form(),
            ];
        }, $this->services);
    }

    /**
     * Convert the collection to an array.
     *
     * @return array<int, class-string>
     */
    public function __array(): array
    {
        return $this->services;
    }
}
