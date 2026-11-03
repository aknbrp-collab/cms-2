<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DocumentStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A file an applicant uploaded for one admission requirement. Stored on the
 * private "local" disk and only served through an authorized admin route.
 *
 * @property string $id
 * @property string $admission_registration_id
 * @property string $admission_requirement_id
 * @property DocumentStatus $status
 * @property string|null $note
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $uploaded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AdmissionRegistration $registration
 * @property-read AdmissionRequirement $requirement
 */
#[Fillable(['admission_registration_id', 'admission_requirement_id'])]
class AdmissionDocument extends Model implements HasMedia
{
    use Auditable, HasUuids, InteractsWithMedia, Prunable;

    public const DISK = 'local';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
            'uploaded_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile()->useDisk(self::DISK);
    }

    /**
     * @return BelongsTo<AdmissionRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(AdmissionRegistration::class, 'admission_registration_id');
    }

    /**
     * @return BelongsTo<AdmissionRequirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(AdmissionRequirement::class, 'admission_requirement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia('file');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->file()?->mime_type, 'image/');
    }

    /**
     * Whether the applicant may upload or replace this file now. Accepted files
     * are final; files that need fixing can always be replaced (unless the
     * registration was not accepted); new uploads need an open period.
     */
    public static function applicantCanUpload(AdmissionRegistration $registration, ?self $document): bool
    {
        if ($document?->status === DocumentStatus::Accepted) {
            return false;
        }

        if ($document?->status === DocumentStatus::NeedsFix) {
            return $registration->status !== RegistrationStatus::Rejected;
        }

        return $registration->status === RegistrationStatus::New && $registration->period->isOpen();
    }

    /**
     * With CMS_ADMISSION_DOCUMENTS_RETENTION_DAYS set, files are deleted that
     * many days after their admission period closed (model:prune, nightly).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('cms.retention.admission_documents');

        if (! is_numeric($days)) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->whereHas('registration.period', fn (Builder $query) => $query->whereDate('closes_on', '<', today()->subDays((int) $days)));
    }
}
