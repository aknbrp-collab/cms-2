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
 * A lecturer or ustadz in the Ma'had Aly directory.
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string $slug
 * @property string|null $nidn
 * @property string|null $position
 * @property string|null $expertise
 * @property string|null $education
 * @property string|null $email
 * @property string|null $photo_id
 * @property string|null $bio
 * @property string|null $external_id
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'slug', 'nidn', 'position', 'expertise', 'education', 'email', 'photo_id', 'bio', 'external_id', 'sort_order'])]
class Lecturer extends Model
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
