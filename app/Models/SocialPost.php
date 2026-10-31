<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Enums\SocialPostStatus;
use App\Enums\SocialTargetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A social media post written once and published to several accounts.
 *
 * @property string $id
 * @property string $site_id
 * @property string|null $author_id
 * @property string $caption
 * @property list<string>|null $media Media asset ids, in order.
 * @property string|null $link
 * @property SocialPostStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'author_id', 'caption', 'media', 'link', 'scheduled_at'])]
class SocialPost extends Model
{
    use Auditable, BelongsToSite, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SocialPostStatus::class,
            'media' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SocialPostTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Media assets of the post, in the chosen order.
     *
     * @return Collection<int, MediaAsset>
     */
    public function mediaAssets(): Collection
    {
        $ids = $this->media ?? [];

        if ($ids === []) {
            return new Collection;
        }

        $assets = MediaAsset::query()->withoutGlobalScopes()->with('media')->whereKey($ids)->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn (string $id) => $assets->get($id), $ids))));
    }

    /**
     * Derive the post status from its targets once publishing has started.
     */
    public function refreshStatus(): void
    {
        $statuses = $this->targets()->pluck('status')->map(fn ($status) => $status instanceof SocialTargetStatus ? $status : SocialTargetStatus::from($status));

        if ($statuses->isEmpty() || $statuses->contains(fn (SocialTargetStatus $status) => in_array($status, [SocialTargetStatus::Pending, SocialTargetStatus::Queued], true))) {
            return;
        }

        $published = $statuses->filter(fn (SocialTargetStatus $status) => $status === SocialTargetStatus::Published)->count();

        $this->forceFill([
            'status' => match (true) {
                $published === $statuses->count() => SocialPostStatus::Published,
                $published === 0 => SocialPostStatus::Failed,
                default => SocialPostStatus::PartiallyFailed,
            },
            'published_at' => $published > 0 ? ($this->published_at ?? now()) : null,
        ])->save();
    }
}
