<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasSeo;
use App\Concerns\HasVisibility;
use App\Concerns\HasWorkflow;
use App\Contracts\Reviewable;
use App\Enums\ContentStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Agenda item: halaqah, exam, symposium, holiday …
 *
 * @property string $id
 * @property bool $is_hidden
 * @property string $site_id
 * @property string|null $author_id
 * @property string $title
 * @property string $slug
 * @property string|null $label
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $location
 * @property string|null $audience
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property bool $all_day
 * @property string|null $featured_image_id
 * @property ContentStatus $status
 * @property Carbon|null $submitted_at
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $publish_at
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id', 'author_id', 'title', 'slug', 'label', 'summary', 'body', 'location', 'audience',
    'starts_at', 'ends_at', 'all_day', 'featured_image_id', 'publish_at',
])]
class Event extends Model implements Reviewable
{
    /** @use HasFactory<EventFactory> */
    use Auditable, BelongsToSite, HasFactory, HasSeo, HasUuids, HasVisibility, HasWorkflow, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'all_day' => false,
    ];

    public static function permissionModule(): string
    {
        return 'events';
    }

    public static function contentLabel(): string
    {
        return __('Event');
    }

    public static function adminEditRoute(): string
    {
        return 'admin.events.edit';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'featured_image_id');
    }

    /**
     * Events that have not ended yet.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('starts_at', '>=', now()->startOfDay())
            ->orWhere('ends_at', '>=', now()));
    }
}
