<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasPublishToggle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An extracurricular activity (Ekstrakurikuler).
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string|null $category
 * @property string|null $description
 * @property string|null $schedule
 * @property string|null $coach
 * @property string|null $image_id
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'category', 'description', 'schedule', 'coach', 'image_id', 'sort_order'])]
class Extracurricular extends Model
{
    use Auditable, BelongsToSite, HasPublishToggle, HasUuids;

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_id');
    }
}
