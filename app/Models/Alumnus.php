<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasPublishToggle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Pondok alumnus in the public alumni directory.
 *
 * @property string $id
 * @property string $site_id
 * @property string $name
 * @property int $graduation_year
 * @property string|null $city
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'name', 'graduation_year', 'city', 'sort_order'])]
class Alumnus extends Model
{
    use Auditable, BelongsToSite, HasPublishToggle, HasUuids;

    protected $table = 'alumni';

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
}
