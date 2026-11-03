<?php

namespace App\Concerns;

use App\Enums\ContentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Editorial workflow: draft → under review → approved (scheduled) → published.
 *
 * Transitions are performed by App\Services\ContentWorkflow. Models using this
 * trait implement App\Contracts\Reviewable.
 *
 * @mixin Model
 */
trait HasWorkflow
{
    public function initializeHasWorkflow(): void
    {
        $this->mergeCasts([
            'status' => ContentStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ]);

        $this->attributes['status'] ??= ContentStatus::Draft->value;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Content visible on the public website: published, released and not hidden
     * (workflow models also use HasVisibility).
     *
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ContentStatus::Published->value)
            ->where($query->qualifyColumn('published_at'), '<=', now())
            ->where($query->qualifyColumn('is_hidden'), false);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithStatus(Builder $query, ContentStatus $status): void
    {
        $query->where($query->qualifyColumn('status'), $status->value);
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    /**
     * Whether the item is approved and waiting for its publish date.
     */
    public function isScheduled(): bool
    {
        return $this->status === ContentStatus::Approved && $this->publish_at?->isFuture();
    }
}
