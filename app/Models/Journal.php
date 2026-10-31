<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasPublishToggle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A scholarly journal published by the institution (usually hosted on OJS).
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string $slug
 * @property string|null $issn
 * @property string|null $e_issn
 * @property string|null $accreditation
 * @property string|null $description
 * @property string|null $url
 * @property string|null $cover_id
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'slug', 'issn', 'e_issn', 'accreditation', 'description', 'url', 'cover_id', 'sort_order'])]
class Journal extends Model
{
    use Auditable, BelongsToSite, HasPublishToggle, HasUuids;

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_id');
    }

    /**
     * @return HasMany<Publication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }
}
