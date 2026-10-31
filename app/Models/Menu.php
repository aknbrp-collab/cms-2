<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Enums\MenuLocation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A navigation menu of a site, one per location.
 *
 * @property string $id
 * @property string $site_id
 * @property MenuLocation $location
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'location', 'name'])]
class Menu extends Model
{
    use Auditable, BelongsToSite, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
        ];
    }

    /**
     * Every item of the menu, in display order.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /**
     * Top-level items, in display order.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    /**
     * The menu of the given site and location, created when missing.
     */
    public static function for(Site $site, MenuLocation $location): self
    {
        return static::withoutSiteScope()->firstOrCreate(
            ['site_id' => $site->id, 'location' => $location->value],
            ['name' => $location->label()],
        );
    }
}
