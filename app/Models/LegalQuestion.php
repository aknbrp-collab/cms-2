<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Enums\LegalCategory;
use App\Enums\LegalQuestionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A "Tanya Hukum" question from a website visitor, answered by scholars in SIAKAD.
 *
 * @property string $id
 * @property string $site_id
 * @property string $tracking_code
 * @property LegalQuestionStatus $status
 * @property LegalCategory $category
 * @property string|null $asker_name
 * @property string|null $asker_email
 * @property string|null $asker_city
 * @property bool $is_anonymous
 * @property string $question
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $public_question
 * @property string|null $moderation_note
 * @property string|null $moderated_by
 * @property string|null $external_id
 * @property Carbon|null $forwarded_at
 * @property string|null $answer
 * @property string|null $answered_by
 * @property string|null $answer_references
 * @property Carbon|null $answered_at
 * @property string|null $pending_answer correction from SIAKAD after publication
 * @property string|null $pending_answered_by
 * @property string|null $pending_answer_references
 * @property Carbon|null $pending_answer_at
 * @property Carbon|null $published_at
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_id', 'category', 'asker_name', 'asker_email', 'asker_city', 'is_anonymous', 'question', 'ip_address'])]
#[Hidden(['asker_email', 'ip_address'])]
class LegalQuestion extends Model
{
    use Auditable, BelongsToSite, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'submitted',
        'is_anonymous' => false,
    ];

    /**
     * Assign the tracking code on insert (also when model events are muted, e.g. in seeders).
     *
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        $this->tracking_code ??= static::nextTrackingCode();

        return parent::performInsert($query);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LegalQuestionStatus::class,
            'category' => LegalCategory::class,
            'is_anonymous' => 'boolean',
            'forwarded_at' => 'datetime',
            'answered_at' => 'datetime',
            'pending_answer_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * Questions visible in the public Tanya Hukum archive.
     *
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', LegalQuestionStatus::Published->value)->whereNotNull('published_at');
    }

    /**
     * Name shown publicly: "Hamba Allah" for anonymous questions.
     */
    public function publicAskerName(): string
    {
        if ($this->is_anonymous || blank($this->asker_name)) {
            return 'Hamba Allah';
        }

        return trim($this->asker_name.($this->asker_city ? ', '.$this->asker_city : ''));
    }

    /**
     * Personal data stays out of the audit log.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['asker_name', 'asker_email', 'asker_city', 'question', 'answer'];
    }

    protected static function nextTrackingCode(): string
    {
        do {
            $code = 'TH-'.Str::upper(Str::random(8));
        } while (static::query()->withoutGlobalScopes()->where('tracking_code', $code)->exists());

        return $code;
    }
}
