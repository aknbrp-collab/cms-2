<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $seoable_type
 * @property string $seoable_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $og_image_id
 * @property string|null $canonical_url
 * @property bool $noindex
 */
#[Fillable(['meta_title', 'meta_description', 'og_image_id', 'canonical_url', 'noindex'])]
class SeoMeta extends Model
{
    use HasUuids;

    protected $table = 'seo_meta';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noindex' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_id');
    }
}
