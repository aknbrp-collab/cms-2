<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasSeo;
use App\Concerns\HasVisibility;
use App\Concerns\HasWorkflow;
use App\Contracts\Reviewable;
use App\Enums\ContentStatus;
use App\Enums\PostType;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * News item, announcement or article.
 *
 * @property string $id
 * @property bool $is_hidden
 * @property string $site_id
 * @property PostType $type
 * @property string|null $category_id
 * @property string|null $author_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $body
 * @property string|null $featured_image_id
 * @property bool $is_featured
 * @property ContentStatus $status
 * @property Carbon|null $submitted_at
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $publish_at
 * @property Carbon|null $published_at
 * @property string|null $external_source
 * @property string|null $external_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id', 'type', 'category_id', 'author_id', 'title', 'slug', 'excerpt', 'body',
    'featured_image_id', 'is_featured', 'publish_at', 'external_source', 'external_id',
])]
class Post extends Model implements Reviewable
{
    /** @use HasFactory<PostFactory> */
    use Auditable, BelongsToSite, HasFactory, HasSeo, HasUuids, HasVisibility, HasWorkflow, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'news',
        'is_featured' => false,
    ];

    public static function permissionModule(): string
    {
        return 'posts';
    }

    public static function adminEditRoute(): string
    {
        return 'admin.posts.edit';
    }

    public static function contentLabel(): string
    {
        return __('Post');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'featured_image_id');
    }
}
