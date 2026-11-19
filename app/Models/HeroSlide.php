<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A picture behind the home page hero, with optional text and button.
 *
 * @property string $id
 * @property bool $is_hidden
 * @property string $site_id
 * @property string|null $image_id
 * @property string|null $label
 * @property string|null $title
 * @property string|null $text
 * @property string|null $button_label
 * @property string|null $button_url
 * @property int $sort_order
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaAsset|null $image
 */
#[Fillable(['site_id', 'image_id', 'label', 'title', 'text', 'button_label', 'button_url', 'sort_order', 'starts_at', 'ends_at'])]
class HeroSlide extends Model
{
    use Auditable, BelongsToSite, HasUuids, HasVisibility;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_id');
    }

    /**
     * Slides shown now: visible, with an image, inside their schedule, in order.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->visible()
            ->whereNotNull('image_id')
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order');
    }

    /**
     * Scheduling state for the admin list.
     */
    public function scheduleState(): string
    {
        return match (true) {
            $this->starts_at?->isFuture() => 'upcoming',
            $this->ends_at?->isPast() => 'ended',
            default => 'running',
        };
    }
}
