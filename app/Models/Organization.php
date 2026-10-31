<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasPublishToggle;
use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student organization (DEMA, UKM).
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string $slug
 * @property OrganizationType $type
 * @property string|null $chairperson
 * @property string|null $period
 * @property string|null $description
 * @property string|null $instagram_url
 * @property string|null $logo_id
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'slug', 'type', 'chairperson', 'period', 'description', 'instagram_url', 'logo_id', 'sort_order'])]
class Organization extends Model
{
    use Auditable, BelongsToSite, HasPublishToggle, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_id');
    }
}
