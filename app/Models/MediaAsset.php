<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Support\CurrentSite;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An item in the central media library (image, video or document).
 *
 * Assets without a site are shared by every site.
 *
 * @property string $id
 * @property string|null $site_id
 * @property string $title
 * @property string|null $alt
 * @property string|null $caption
 * @property string|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'title', 'alt', 'caption', 'uploaded_by'])]
class MediaAsset extends Model implements HasMedia
{
    /** @use HasFactory<MediaAssetFactory> */
    use Auditable, HasFactory, HasUuids, InteractsWithMedia;

    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

    public const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'mp4', 'webm', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    protected static function booted(): void
    {
        // Show assets of the current site plus shared assets.
        static::addGlobalScope('site', function (Builder $query) {
            $site = app(CurrentSite::class);

            if ($site->has()) {
                $query->where(fn (Builder $query) => $query
                    ->where($query->qualifyColumn('site_id'), $site->id())
                    ->orWhereNull($query->qualifyColumn('site_id')));
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media === null || ! in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return;
        }

        $this->addMediaConversion('thumb')->fit(Fit::Crop, 400, 300)->format('webp');
        $this->addMediaConversion('large')->fit(Fit::Max, 1600, 1600)->format('webp');
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia('file');
    }

    public function isImage(): bool
    {
        return in_array($this->file()?->mime_type, self::IMAGE_TYPES, true);
    }

    /**
     * Public URL of the file or one of its image conversions.
     */
    public function url(string $conversion = ''): string
    {
        $media = $this->file();

        if ($media === null) {
            return '';
        }

        return $conversion !== '' && $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }
}
