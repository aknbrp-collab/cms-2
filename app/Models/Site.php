<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\SiteKey;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property SiteKey $key
 * @property string $name
 * @property string $domain
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'domain', 'is_active', 'settings'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use Auditable, HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => SiteKey::class,
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Find a site by its key.
     */
    public static function findByKey(SiteKey $key): ?self
    {
        return static::query()->where('key', $key->value)->first();
    }

    /**
     * Determine if the given content module is enabled for this site.
     */
    public function hasModule(string $module): bool
    {
        return in_array($module, config("cms.sites.{$this->key->value}.modules", []), true);
    }
}
