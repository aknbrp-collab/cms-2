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
 * Pengasuh, Mudir, Dewan Masyayikh and other leadership profiles.
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string $position
 * @property string|null $expertise
 * @property string|null $photo_id
 * @property string|null $bio
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'position', 'expertise', 'photo_id', 'bio', 'sort_order'])]
class Leader extends Model
{
    use Auditable, BelongsToSite, HasPublishToggle, HasUuids;

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_id');
    }
}
