<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasPublishToggle;
use App\Enums\ProgramLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Takhassus study program with its curriculum.
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string $slug
 * @property ProgramLevel $level
 * @property string|null $degree
 * @property string|null $accreditation
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $core_books
 * @property string|null $image_id
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'slug', 'level', 'degree', 'accreditation', 'summary', 'description', 'core_books', 'image_id', 'sort_order'])]
class Program extends Model
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
            'level' => ProgramLevel::class,
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
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class)->orderBy('semester')->orderBy('sort_order');
    }

    /**
     * Core books (kitab muqarrar), one per line.
     *
     * @return list<string>
     */
    public function coreBookList(): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $this->core_books))));
    }
}
