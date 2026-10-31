<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\Role;
use App\Enums\SiteKey;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, HasUuids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
        ];
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole(Role::Superadmin->value);
    }

    /**
     * The CMS roles assigned to this user.
     *
     * @return list<Role>
     */
    public function cmsRoles(): array
    {
        return array_values($this->getRoleNames()
            ->map(fn (string $name) => Role::tryFrom($name))
            ->filter()
            ->all());
    }

    /**
     * Keys of the sites this user may act on, derived from their roles.
     *
     * @return list<SiteKey>
     */
    public function accessibleSiteKeys(): array
    {
        $keys = [];

        foreach ($this->cmsRoles() as $role) {
            if ($role->site() === null) {
                return SiteKey::cases();
            }

            $keys[$role->site()->value] = $role->site();
        }

        return array_values($keys);
    }

    /**
     * The sites this user may act on.
     *
     * @return Collection<int, Site>
     */
    public function accessibleSites(): Collection
    {
        return Site::query()
            ->whereIn('key', array_map(fn (SiteKey $key) => $key->value, $this->accessibleSiteKeys()))
            ->orderBy('name')
            ->get();
    }

    public function canAccessSite(Site $site): bool
    {
        return in_array($site->key, $this->accessibleSiteKeys(), true);
    }

    /**
     * Determine if the user may switch the admin panel to "all sites".
     */
    public function canAccessAllSites(): bool
    {
        return count($this->accessibleSiteKeys()) === count(SiteKey::cases());
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
