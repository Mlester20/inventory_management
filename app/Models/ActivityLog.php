<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
    ];

    /**
     * Get the user that owns this activity log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a user activity.
     * 
     * @param int $userId
     * @param string $action
     * @param string|null $description
     * @param string|null $ipAddress
     * @return self
     */
    public static function logActivity(int $userId, string $action, ?string $description = null, ?string $ipAddress = null): self
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Log user login activity (works for all users including admins).
     * 
     * @param int $userId
     * @param string|null $ipAddress
     * @return self
     */
    public static function logLogin(int $userId, ?string $ipAddress = null): self
    {
        return self::logActivity(
            $userId,
            'login',
            'User logged in',
            $ipAddress
        );
    }

    /**
     * Log user logout activity (works for all users including admins).
     * 
     * @param int $userId
     * @param string|null $ipAddress
     * @return self
     */
    public static function logLogout(int $userId, ?string $ipAddress = null): self
    {
        return self::logActivity(
            $userId,
            'logout',
            'User logged out',
            $ipAddress
        );
    }
}
