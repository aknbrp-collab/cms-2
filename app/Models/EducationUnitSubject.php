<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A subject (mata pelajaran) in an education unit's curriculum.
 *
 * @property string $id
 * @property string $education_unit_id
 * @property string $grade
 * @property string $name
 * @property string|null $books
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['education_unit_id', 'grade', 'name', 'books', 'sort_order'])]
class EducationUnitSubject extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<EducationUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }
}
