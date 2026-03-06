<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Activity\Models;

use Core\Activity\Scopes\ActivityScopes;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Extended Activity model with workspace-aware scopes.
 *
 * This model extends Spatie's Activity model to add workspace scoping
 * and additional query scopes for the Core PHP framework.
 *
 * To use this model instead of Spatie's default, add to your
 * config/activitylog.php:
 *
 *   'activity_model' => \Core\Activity\Models\Activity::class,
 *
 * @method static \Illuminate\Database\Eloquent\Builder forWorkspace(\Illuminate\Database\Eloquent\Model|int $workspace)
 * @method static \Illuminate\Database\Eloquent\Builder forSubject(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \Illuminate\Database\Eloquent\Builder forSubjectType(string $subjectType)
 * @method static \Illuminate\Database\Eloquent\Builder byCauser(\Illuminate\Contracts\Auth\Authenticatable|\Illuminate\Database\Eloquent\Model $user)
 * @method static \Illuminate\Database\Eloquent\Builder byCauserId(int $causerId, string|null $causerType = null)
 * @method static \Illuminate\Database\Eloquent\Builder ofType(string|array $event)
 * @method static \Illuminate\Database\Eloquent\Builder createdEvents()
 * @method static \Illuminate\Database\Eloquent\Builder updatedEvents()
 * @method static \Illuminate\Database\Eloquent\Builder deletedEvents()
 * @method static \Illuminate\Database\Eloquent\Builder betweenDates(\DateTimeInterface|string $from, \DateTimeInterface|string|null $to = null)
 * @method static \Illuminate\Database\Eloquent\Builder today()
 * @method static \Illuminate\Database\Eloquent\Builder lastDays(int $days)
 * @method static \Illuminate\Database\Eloquent\Builder lastHours(int $hours)
 * @method static \Illuminate\Database\Eloquent\Builder search(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder inLog(string $logName)
 * @method static \Illuminate\Database\Eloquent\Builder withChanges()
 * @method static \Illuminate\Database\Eloquent\Builder withExistingSubject()
 * @method static \Illuminate\Database\Eloquent\Builder withDeletedSubject()
 * @method static \Illuminate\Database\Eloquent\Builder newest()
 * @method static \Illuminate\Database\Eloquent\Builder oldest()
 */
class Activity extends SpatieActivity
{
    use ActivityScopes;

    /**
     * Get the workspace ID from properties.
     */
    public function getWorkspaceIdAttribute(): ?int
    {
        return $this->properties->get('workspace_id');
    }

    /**
     * Get the old values from properties.
     *
     * @return array<string, mixed>
     */
    public function getOldValuesAttribute(): array
    {
        return $this->properties->get('old', []);
    }

    /**
     * Get the new values from properties.
     *
     * @return array<string, mixed>
     */
    public function getNewValuesAttribute(): array
    {
        return $this->properties->get('attributes', []);
    }

    /**
     * Get the changed attributes.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChangesAttribute(): array
    {
        $old = $this->old_values;
        $new = $this->new_values;
        $changes = [];

        foreach ($new as $key => $newValue) {
            $oldValue = $old[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if this activity has any changes.
     */
    public function hasChanges(): bool
    {
        return ! empty($this->new_values) || ! empty($this->old_values);
    }

    /**
     * Get a human-readable summary of changes.
     */
    public function getChangesSummary(): string
    {
        $changes = $this->changes;

        if (empty($changes)) {
            return 'No changes recorded';
        }

        $parts = [];
        foreach ($changes as $field => $values) {
            $parts[] = sprintf(
                '%s: %s -> %s',
                $field,
                $this->formatValue($values['old']),
                $this->formatValue($values['new'])
            );
        }

        return implode(', ', $parts);
    }

    /**
     * Format a value for display.
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * Get the display name for the causer.
     */
    public function getCauserNameAttribute(): string
    {
        $causer = $this->causer;

        if (! $causer) {
            return 'System';
        }

        return $causer->name ?? $causer->email ?? 'User #'.$causer->getKey();
    }

    /**
     * Get the display name for the subject.
     */
    public function getSubjectNameAttribute(): ?string
    {
        $subject = $this->subject;

        if (! $subject) {
            return null;
        }

        // Try common name attributes
        foreach (['name', 'title', 'label', 'email', 'slug'] as $attribute) {
            if (isset($subject->{$attribute})) {
                return (string) $subject->{$attribute};
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    /**
     * Get the subject type as a readable name.
     */
    public function getSubjectTypeNameAttribute(): ?string
    {
        return $this->subject_type ? class_basename($this->subject_type) : null;
    }
}
