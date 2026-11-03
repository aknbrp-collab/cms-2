<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasSeo;
use App\Concerns\HasVisibility;
use App\Concerns\HasWorkflow;
use App\Contracts\Reviewable;
use App\Enums\ContentStatus;
use App\Enums\PublicationType;
use Database\Factories\PublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An item in the research repository: article, journal article, Bahtsul
 * Masail decision, thesis or book. May come from SIAKAD (external_id).
 *
 * @property string $id
 * @property bool $is_hidden
 * @property string $site_id
 * @property string|null $author_id
 * @property PublicationType $type
 * @property string $title
 * @property string $slug
 * @property string $authors
 * @property string|null $abstract
 * @property string|null $body
 * @property int $year
 * @property string|null $journal_id
 * @property string|null $volume
 * @property string|null $issue
 * @property string|null $pages
 * @property string|null $doi
 * @property string|null $keywords
 * @property string|null $file_id
 * @property string|null $external_url
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
    'site_id', 'author_id', 'type', 'title', 'slug', 'authors', 'abstract', 'body', 'year', 'journal_id',
    'volume', 'issue', 'pages', 'doi', 'keywords', 'file_id', 'external_url', 'publish_at',
    'external_source', 'external_id',
])]
class Publication extends Model implements Reviewable
{
    /** @use HasFactory<PublicationFactory> */
    use Auditable, BelongsToSite, HasFactory, HasSeo, HasUuids, HasVisibility, HasWorkflow, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'research',
    ];

    public static function permissionModule(): string
    {
        return 'publications';
    }

    public static function contentLabel(): string
    {
        return __('Publication');
    }

    public static function adminEditRoute(): string
    {
        return 'admin.publications.edit';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PublicationType::class,
            'year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'file_id');
    }

    /**
     * Short citation, e.g. "Al-Ahkam, Vol. 12 No. 1 (2024), 1-20".
     */
    public function citation(): string
    {
        $parts = array_filter([
            $this->journal?->name,
            $this->volume ? "Vol. {$this->volume}" : null,
            $this->issue ? "No. {$this->issue}" : null,
        ]);

        $citation = implode(', ', $parts);
        $citation .= ($citation !== '' ? ' ' : '')."({$this->year})";

        return $this->pages ? "{$citation}, {$this->pages}" : $citation;
    }
}
