<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasVisibility;
use App\Enums\MenuItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property bool $is_hidden
 * @property string $menu_id
 * @property string|null $parent_id
 * @property string $label
 * @property MenuItemType $type
 * @property string|null $url
 * @property string|null $linkable_type
 * @property string|null $linkable_id
 * @property bool $open_in_new_tab
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['menu_id', 'parent_id', 'label', 'type', 'url', 'linkable_type', 'linkable_id', 'open_in_new_tab', 'sort_order'])]
class MenuItem extends Model
{
    use Auditable, HasUuids, HasVisibility;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MenuItemType::class,
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether the page or category the item links to is shown on the public site.
     */
    public function targetIsPublic(): bool
    {
        $linkable = $this->linkable;

        return match ($this->type) {
            MenuItemType::Url => true,
            MenuItemType::Page => $linkable instanceof Page && $linkable->isPublished() && ! $linkable->isHidden(),
            MenuItemType::Category => $linkable instanceof Category && ! $linkable->isHidden(),
        };
    }

    /**
     * Site-relative or absolute URL the item points to.
     */
    public function href(): string
    {
        $linkable = $this->linkable;

        return match ($this->type) {
            MenuItemType::Url => (string) $this->url,
            MenuItemType::Page => $linkable instanceof Page ? '/'.$linkable->slug : '#',
            MenuItemType::Category => $linkable instanceof Category ? '/kategori/'.$linkable->slug : '#',
        };
    }
}
