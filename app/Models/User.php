<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Passwords\CanResetPassword;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_picture',
        'is_active',
        'is_encoder',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_encoder' => 'boolean',
        ];
    }

    /** Accounts that can actually sign in — everything except Encoders. */
    public function scopeLoginAccounts($query)
    {
        return $query->where('is_encoder', false);
    }

    /**
     * The names offered under "Prepared By": every login account, plus the Encoders that are still
     * listed. $keep (an id or a list of ids) keeps a document's current preparer selectable even if
     * that Encoder was removed from the list since.
     */
    public function scopePreparers($query, int|array|null $keep = null)
    {
        $keep = array_filter((array) $keep);

        return $query->where(function ($q) use ($keep) {
            $q->where('is_encoder', false)->orWhere('is_active', true);
            if ($keep !== []) {
                $q->orWhereIn('id', $keep);
            }
        })->orderBy('name');
    }

    /**
     * Full URL to the user's profile picture, falling back to the app's
     * default placeholder avatar when none has been uploaded.
     */
    public function getProfilePictureUrlAttribute(): string
    {
        return $this->profile_picture
            ? asset('storage/' . $this->profile_picture)
            : asset('assets/img/avatars/1.png');
    }
}
