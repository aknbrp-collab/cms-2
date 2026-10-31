<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Enums\SocialProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A connected Facebook Page, Instagram Business account or TikTok account.
 * Tokens are encrypted at rest and never leave the server.
 *
 * @property string $id
 * @property string $site_id
 * @property SocialProvider $provider
 * @property string $external_id
 * @property string|null $provider_user_id
 * @property string $name
 * @property string|null $handle
 * @property string|null $avatar_url
 * @property string $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $refresh_expires_at
 * @property list<string>|null $scopes
 * @property array<string, mixed>|null $meta
 * @property string $status active|expired|revoked|error
 * @property string|null $last_error
 * @property string|null $connected_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id', 'provider', 'external_id', 'provider_user_id', 'name', 'handle', 'avatar_url',
    'access_token', 'refresh_token', 'token_expires_at', 'refresh_expires_at', 'scopes', 'meta', 'connected_by',
])]
#[Hidden(['access_token', 'refresh_token'])]
class SocialAccount extends Model
{
    use Auditable, BelongsToSite, HasUuids;

    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const REVOKED = 'revoked';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'scopes' => 'array',
            'meta' => 'array',
        ];
    }

    /**
     * @return HasMany<SocialPostTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isUsable(): bool
    {
        return $this->status === self::ACTIVE
            && ($this->token_expires_at === null || $this->token_expires_at->isFuture() || $this->refresh_token !== null);
    }

    public function displayName(): string
    {
        return $this->handle ? "{$this->name} (@{$this->handle})" : $this->name;
    }
}
