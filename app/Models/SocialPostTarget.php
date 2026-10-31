<?php

namespace App\Models;

use App\Enums\SocialProvider;
use App\Enums\SocialTargetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One account a social post is published to, with platform-specific tweaks.
 *
 * @property string $id
 * @property string $social_post_id
 * @property string $social_account_id
 * @property string|null $caption_override
 * @property string|null $hashtags
 * @property array<string, mixed>|null $options TikTok: privacy_level, disable_comment, disable_duet, disable_stitch, brand_organic, brand_content.
 * @property SocialTargetStatus $status
 * @property string|null $external_post_id
 * @property string|null $permalink
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['social_post_id', 'social_account_id', 'caption_override', 'hashtags', 'options'])]
class SocialPostTarget extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SocialTargetStatus::class,
            'options' => 'array',
            'attempts' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SocialPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Caption as published on this account: override or post caption, plus hashtags.
     */
    public function finalCaption(): string
    {
        $caption = trim((string) ($this->caption_override ?: $this->post->caption));
        $hashtags = self::normalizeHashtags($this->hashtags);

        return $hashtags === '' ? $caption : $caption."\n\n".$hashtags;
    }

    public function provider(): SocialProvider
    {
        return $this->account->provider;
    }

    /**
     * "dakwah, #santri pesantren" → "#dakwah #santri #pesantren".
     */
    public static function normalizeHashtags(?string $hashtags): string
    {
        return collect(preg_split('/[\s,]+/', (string) $hashtags) ?: [])
            ->map(fn (string $tag) => ltrim(trim($tag), '#'))
            ->filter()
            ->unique()
            ->map(fn (string $tag) => '#'.$tag)
            ->join(' ');
    }
}
