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
use Illuminate\Support\Collection;

/**
 * A Pondok education unit (Program Pendidikan), e.g. Tahfidz or PDF Ulya,
 * with its student count and curriculum.
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string $slug
 * @property string|null $summary
 * @property int|null $student_count
 * @property string|null $description
 * @property string|null $image_id
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'slug', 'summary', 'student_count', 'description', 'image_id', 'sort_order'])]
class EducationUnit extends Model
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
            'student_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_id');
    }

    /**
     * @return HasMany<EducationUnitSubject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(EducationUnitSubject::class)->orderBy('sort_order');
    }

    /**
     * Curriculum rows grouped by class/level, in the order the levels were added.
     *
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, EducationUnitSubject>>
     */
    public function curriculum(): Collection
    {
        /** @var Collection<string, \Illuminate\Database\Eloquent\Collection<int, EducationUnitSubject>> */
        return $this->subjects->groupBy('grade')->toBase();
    }
}
