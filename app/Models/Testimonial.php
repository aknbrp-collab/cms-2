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
 * A testimonial from a Pondok alumnus.
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property int|null $graduation_year
 * @property string|null $occupation
 * @property string $quote
 * @property string|null $photo_id
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'graduation_year', 'occupation', 'quote', 'photo_id', 'sort_order'])]
class Testimonial extends Model
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
            'graduation_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_id');
    }
}
