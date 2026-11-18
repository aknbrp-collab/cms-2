<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Enums\PublicationType;
use App\Enums\SiakadArticleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An academic article pushed by SIAKAD, waiting for approval before it is
 * published on the website as a Publication.
 *
 * @property string $id
 * @property string $site_id
 * @property string $external_id
 * @property SiakadArticleStatus $status
 * @property PublicationType $type
 * @property string $title
 * @property string $authors
 * @property string|null $abstract
 * @property string|null $body
 * @property int $year
 * @property string|null $keywords
 * @property string|null $doi
 * @property string|null $file_url
 * @property string|null $source_url
 * @property string|null $image_url picture link sent by SIAKAD (contract 1.2)
 * @property string|null $image_caption
 * @property string|null $image_asset_id the CMS's own copy in the media library
 * @property string|null $image_source_url the image_url that copy was made from
 * @property string|null $image_error why the last download failed
 * @property array<string, mixed> $payload
 * @property Carbon $received_at
 * @property bool $updated_after_approval
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property string|null $publication_id
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id', 'external_id', 'type', 'title', 'authors', 'abstract', 'body', 'year', 'keywords',
    'doi', 'file_url', 'source_url', 'image_url', 'image_caption', 'payload', 'received_at',
])]
class SiakadArticle extends Model
{
    use Auditable, BelongsToSite, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'updated_after_approval' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SiakadArticleStatus::class,
            'type' => PublicationType::class,
            'year' => 'integer',
            'payload' => 'array',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'updated_after_approval' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_asset_id');
    }

    /**
     * Whether the picture SIAKAD sent still has to be downloaded.
     */
    public function needsImageDownload(): bool
    {
        return $this->image_url !== null && $this->image_url !== $this->image_source_url;
    }

    /**
     * @return BelongsTo<Publication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The raw payload is large and already stored; keep it out of the audit log.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['payload', 'body'];
    }
}
