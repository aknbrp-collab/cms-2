<?php

namespace App\Models;

use App\Support\Embed;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A photo (media asset) or a YouTube video in a gallery.
 *
 * @property string $id
 * @property string $gallery_id
 * @property string|null $media_asset_id
 * @property string|null $video_url
 * @property string|null $caption
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['gallery_id', 'media_asset_id', 'video_url', 'caption', 'sort_order'])]
class GalleryItem extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<Gallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function embedUrl(): ?string
    {
        return Embed::toEmbedUrl($this->video_url);
    }

    /**
     * YouTube thumbnail for video items.
     */
    public function videoThumbnail(): ?string
    {
        $embed = $this->embedUrl();

        return $embed !== null && str_contains($embed, '/embed/')
            ? 'https://i.ytimg.com/vi/'.basename($embed).'/hqdefault.jpg'
            : null;
    }
}
