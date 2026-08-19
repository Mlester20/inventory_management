<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_POS = 'pos';
    public const SOURCE_SYSTEM = 'system';

    /**
     * Metadata keys that are never persisted even if a caller passes them —
     * a safety net alongside the convention of not passing sensitive data,
     * per the refactor's constraint against logging plaintext secrets.
     */
    private const REDACTED_METADATA_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'remember_token', 'token', 'secret', 'api_key',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'role',
        'module',
        'action',
        'source',
        'description',
        'loggable_type',
        'loggable_id',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns this activity log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record this activity was performed on, if any (e.g. the Customer
     * that was created/updated, the DeliveryReceipt that was saved).
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The single entry point every module should call to write an activity
     * log entry — see docs/activity-log-refactor/SCHEMA_NOTES.md for the
     * full usage guide. Centralizing here (rather than scattering raw
     * ActivityLog::create() calls with inconsistent fields across
     * controllers) is what keeps every module's logs consistently shaped.
     *
     * @param string $module e.g. "Customer", "DeliveryReceipt", "Auth" — the controlled vocabulary lives in SCHEMA_NOTES.md
     * @param string $action e.g. "created", "updated", "deleted", "login" — see SCHEMA_NOTES.md
     * @param \Illuminate\Database\Eloquent\Model|null $loggable The affected record, if any
     * @param string|null $description Human-readable summary, e.g. "Created customer Green Cross Pharmacy"
     * @param array|null $metadata Structured context (e.g. ['before' => [...], 'after' => [...]] on an update) — never sensitive data
     * @param string $source self::SOURCE_ADMIN (default), self::SOURCE_POS, or self::SOURCE_SYSTEM
     * @param int|null $userId Defaults to the currently authenticated user
     */
    public static function record(
        string $module,
        string $action,
        ?Model $loggable = null,
        ?string $description = null,
        ?array $metadata = null,
        string $source = self::SOURCE_ADMIN,
        ?int $userId = null,
    ): self {
        $userId = $userId ?? Auth::id();

        // A snapshot, not a live lookup — resolved from whichever user_id is
        // actually being credited with this action (the current session's
        // user in the common case, but some callers thread through a
        // specific $userId instead), so it's correct even when record() is
        // called outside the request that authenticated the actor.
        $role = $userId === Auth::id()
            ? Auth::user()?->role
            : User::find($userId)?->role;

        return static::create([
            'user_id' => $userId,
            'role' => $role,
            'module' => $module,
            'action' => $action,
            'source' => $source,
            'description' => $description,
            'loggable_type' => $loggable?->getMorphClass(),
            'loggable_id' => $loggable?->getKey(),
            'metadata' => static::redactSensitiveKeys($metadata),
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Strip common sensitive key names from metadata before it's persisted
     * — a safety net on top of the convention that callers should never
     * pass these in the first place.
     */
    private static function redactSensitiveKeys(?array $metadata): ?array
    {
        if (! $metadata) {
            return $metadata;
        }

        array_walk_recursive($metadata, function (&$value, $key) {
            if (in_array(strtolower((string) $key), self::REDACTED_METADATA_KEYS, true)) {
                $value = '[REDACTED]';
            }
        });

        return $metadata;
    }
}
