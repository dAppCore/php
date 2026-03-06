<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Bouncer\Gate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Action request audit log entry.
 *
 * Records all action permission checks for auditing and training purposes.
 *
 * @property int $id
 * @property string $method HTTP method (GET, POST, etc.)
 * @property string $route Request path
 * @property string $action Action identifier
 * @property string|null $scope Resource scope
 * @property string $guard Guard name
 * @property string|null $role User's role at time of request
 * @property int|null $user_id User ID if authenticated
 * @property string|null $ip_address Client IP
 * @property string $status Result: 'allowed', 'denied', 'pending'
 * @property bool $was_trained Whether this request triggered training
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ActionRequest extends Model
{
    protected $table = 'core_action_requests';

    protected $fillable = [
        'method',
        'route',
        'action',
        'scope',
        'guard',
        'role',
        'user_id',
        'ip_address',
        'status',
        'was_trained',
    ];

    protected $casts = [
        'was_trained' => 'boolean',
    ];

    /**
     * Status constants.
     */
    public const STATUS_ALLOWED = 'allowed';

    public const STATUS_DENIED = 'denied';

    public const STATUS_PENDING = 'pending';

    /**
     * User who made the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    /**
     * Log an action request.
     */
    public static function log(
        string $method,
        string $route,
        string $action,
        string $guard,
        string $status,
        ?string $scope = null,
        ?string $role = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        bool $wasTrained = false
    ): self {
        return static::create([
            'method' => $method,
            'route' => $route,
            'action' => $action,
            'scope' => $scope,
            'guard' => $guard,
            'role' => $role,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'status' => $status,
            'was_trained' => $wasTrained,
        ]);
    }

    /**
     * Get pending requests (for training review).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function pending(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('status', self::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get denied requests for an action.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function deniedFor(string $action): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('action', $action)
            ->where('status', self::STATUS_DENIED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get requests by user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unique actions that were denied (candidates for training).
     *
     * @return array<string, array{action: string, count: int, last_at: string}>
     */
    public static function deniedActionsSummary(): array
    {
        return static::where('status', self::STATUS_DENIED)
            ->selectRaw('action, COUNT(*) as count, MAX(created_at) as last_at')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get()
            ->keyBy('action')
            ->map(fn ($row) => [
                'action' => $row->action,
                'count' => (int) $row->count,
                'last_at' => $row->last_at,
            ])
            ->toArray();
    }

    /**
     * Prune old request logs.
     */
    public static function prune(int $days = 30): int
    {
        return static::where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Mark this request as having triggered training.
     */
    public function markTrained(): self
    {
        $this->update(['was_trained' => true]);

        return $this;
    }
}
