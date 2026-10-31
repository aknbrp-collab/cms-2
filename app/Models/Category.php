<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Post category. Categories flagged "show on home" become homepage sections.
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $show_on_home
 * @property int $home_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'slug', 'description', 'show_on_home', 'home_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use Auditable, BelongsToSite, HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_on_home' => 'boolean',
            'home_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
