<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Helpers;

use DateTime;
use DateTimeZone;

/**
 * Timezone list generator with offset formatting.
 *
 * Provides formatted timezone lists grouped by continent,
 * with optional GMT offset display.
 */
class TimezoneList
{
    protected bool $splitGroup = false;

    protected bool $includeGeneral = true;

    protected bool $showOffset = true;

    protected string $offsetPrefix = 'GMT';

    /** @var array<int, string> */
    protected array $generalTimezones = [
        'UTC',
    ];

    /** @var array<string, int> */
    protected array $continents = [
        'Africa' => DateTimeZone::AFRICA,
        'America' => DateTimeZone::AMERICA,
        'Antarctica' => DateTimeZone::ANTARCTICA,
        'Arctic' => DateTimeZone::ARCTIC,
        'Asia' => DateTimeZone::ASIA,
        'Atlantic' => DateTimeZone::ATLANTIC,
        'Australia' => DateTimeZone::AUSTRALIA,
        'Europe' => DateTimeZone::EUROPE,
        'Indian' => DateTimeZone::INDIAN,
        'Pacific' => DateTimeZone::PACIFIC,
    ];

    /**
     * Enable grouping by continent.
     */
    public function splitGroup(bool $status = true): self
    {
        $this->splitGroup = $status;

        return $this;
    }

    /**
     * Include general timezones (UTC, etc).
     */
    public function includeGeneral(bool $status = true): self
    {
        $this->includeGeneral = $status;

        return $this;
    }

    /**
     * Show GMT offset in timezone labels.
     */
    public function showOffset(bool $status = true): self
    {
        $this->showOffset = $status;

        return $this;
    }

    /**
     * Generate timezone list.
     *
     * @return array<string, string|array<string, string>>
     */
    public function list(): array
    {
        $list = [];

        // Flat list (no grouping)
        if (! $this->splitGroup) {
            if ($this->includeGeneral) {
                foreach ($this->generalTimezones as $timezone) {
                    $list[$timezone] = $timezone;
                }
            }

            foreach ($this->continents as $continent => $mask) {
                $timezones = DateTimeZone::listIdentifiers($mask);

                foreach ($timezones as $timezone) {
                    $list[$timezone] = $this->formatTimezone($timezone);
                }
            }

            return $list;
        }

        // Grouped by continent
        if ($this->includeGeneral) {
            foreach ($this->generalTimezones as $timezone) {
                $list['General'][$timezone] = $timezone;
            }
        }

        foreach ($this->continents as $continent => $mask) {
            $timezones = DateTimeZone::listIdentifiers($mask);

            foreach ($timezones as $timezone) {
                $list[$continent][$timezone] = $this->formatTimezone($timezone, $continent);
            }
        }

        return $list;
    }

    /**
     * Format timezone with optional offset and continent removal.
     */
    protected function formatTimezone(string $timezone, ?string $cutOffContinent = null): string
    {
        $displayedTimezone = empty($cutOffContinent)
            ? $timezone
            : substr($timezone, strlen($cutOffContinent) + 1);

        $normalizedTimezone = $this->normalizeTimezone($displayedTimezone);

        if (! $this->showOffset) {
            return $normalizedTimezone;
        }

        $separator = $this->normalizeSeparator();

        return '('.$this->offsetPrefix.$this->getOffset($timezone).')'.$separator.$normalizedTimezone;
    }

    /**
     * Normalise timezone name (replace underscores, etc).
     */
    protected function normalizeTimezone(string $timezone): string
    {
        $search = ['St_', '/', '_'];
        $replace = ['St. ', ' / ', ' '];

        return str_replace($search, $replace, $timezone);
    }

    /**
     * Get separator between offset and timezone name.
     */
    protected function normalizeSeparator(): string
    {
        return ' ';
    }

    /**
     * Get GMT offset for timezone (e.g., +01:00).
     */
    protected function getOffset(string $timezone): string
    {
        $time = new DateTime('', new DateTimeZone($timezone));

        return $time->format('P');
    }
}
