<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasPublishToggle;
use App\Enums\AchievementLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An achievement (prestasi) won by a student or team.
 *
 * @property string $id
 * @property string $site_id
 * @property string $title
 * @property string $student_name
 * @property AchievementLevel $level
 * @property string|null $event_name
 * @property Carbon $achieved_on
 * @property string|null $description
 * @property string|null $image_id
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'title', 'student_name', 'level', 'event_name', 'achieved_on', 'description', 'image_id', 'sort_order'])]
class Achievement extends Model
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
            'level' => AchievementLevel::class,
            'achieved_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_id');
    }
}
