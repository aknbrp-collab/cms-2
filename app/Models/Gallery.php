<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasSeo;
use App\Concerns\HasWorkflow;
use App\Contracts\Reviewable;
use App\Enums\ContentStatus;
use App\Enums\GalleryType;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Photo album or video collection (e.g. the pesantren "TV" channel).
 *
 * @property string $id
 * @property string $site_id
 * @property string|null $author_id
 * @property GalleryType $type
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $taken_on
 * @property string|null $cover_image_id
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
#[Fillable(['site_id', 'author_id', 'type', 'title', 'slug', 'description', 'taken_on', 'cover_image_id', 'publish_at'])]
class Gallery extends Model implements Reviewable
{
    /** @use HasFactory<GalleryFactory> */
    use Auditable, BelongsToSite, HasFactory, HasSeo, HasUuids, HasWorkflow, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'photo',
    ];

    public static function permissionModule(): string
    {
        return 'galleries';
    }

    public static function contentLabel(): string
    {
        return __('Gallery');
    }

    public static function adminEditRoute(): string
    {
        return 'admin.galleries.edit';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GalleryType::class,
            'taken_on' => 'date',
        ];
    }

    /**
     * @return HasMany<GalleryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_image_id');
    }

    /**
     * The explicit cover, or the first photo of the album.
     */
    public function cover(): ?MediaAsset
    {
        return $this->coverImage ?? $this->items->first(fn (GalleryItem $item) => $item->mediaAsset !== null)?->mediaAsset;
    }
}
