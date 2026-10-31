<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasSeo;
use App\Concerns\HasWorkflow;
use App\Contracts\Reviewable;
use App\Enums\ContentStatus;
use App\Enums\PageTemplate;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Static page built from content blocks (About, History, Vision & Mission …).
 *
 * @property string $id
 * @property string $site_id
 * @property string|null $parent_id
 * @property string|null $author_id
 * @property string $title
 * @property string $slug
 * @property PageTemplate $template
 * @property list<array{id: string, type: string, data: array<string, mixed>}>|null $blocks
 * @property int $sort_order
 * @property ContentStatus $status
 * @property Carbon|null $submitted_at
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $publish_at
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'parent_id', 'author_id', 'title', 'slug', 'template', 'blocks', 'sort_order', 'publish_at'])]
class Page extends Model implements Reviewable
{
    /** @use HasFactory<PageFactory> */
    use Auditable, BelongsToSite, HasFactory, HasSeo, HasUuids, HasWorkflow, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'template' => 'default',
        'sort_order' => 0,
    ];

    public static function permissionModule(): string
    {
        return 'pages';
    }

    public static function adminEditRoute(): string
    {
        return 'admin.pages.edit';
    }

    public static function contentLabel(): string
    {
        return __('Page');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => PageTemplate::class,
            'blocks' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort_order');
    }
}
