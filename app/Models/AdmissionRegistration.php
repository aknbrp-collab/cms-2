<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Enums\DocumentStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An online admission form submitted from the public website.
 *
 * @property string $id
 * @property string $site_id
 * @property string $admission_period_id
 * @property string $registration_number
 * @property RegistrationStatus $status
 * @property string $full_name
 * @property string $gender
 * @property string $birth_place
 * @property Carbon $birth_date
 * @property string|null $nisn
 * @property string $previous_school
 * @property string|null $program
 * @property string $parent_name
 * @property string $parent_phone
 * @property string|null $email
 * @property string $address
 * @property string|null $admin_notes
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AdmissionPeriod $period
 */
#[Fillable([
    'site_id', 'admission_period_id', 'full_name', 'gender', 'birth_place', 'birth_date', 'nisn',
    'previous_school', 'program', 'parent_name', 'parent_phone', 'email', 'address', 'ip_address',
])]
#[Hidden(['ip_address'])]
class AdmissionRegistration extends Model
{
    use Auditable, BelongsToSite, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * Assign the number on insert (also when model events are muted, e.g. in seeders).
     *
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        $this->registration_number ??= static::nextNumber();

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
            'status' => RegistrationStatus::class,
            'birth_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AdmissionPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /**
     * @return HasMany<AdmissionDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    /**
     * WhatsApp messages to the guardian, newest first.
     *
     * @return HasMany<AdmissionMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AdmissionMessage::class)->latest();
    }

    /**
     * Upload progress against the period's requirements. Expects
     * period.documentRequirements and documents to be loaded for lists.
     *
     * @return array{total: int, required: int, uploaded: int, missing_required: int, accepted: int, needs_fix: int}
     */
    public function documentSummary(): array
    {
        $requirements = $this->period->documentRequirements;
        $documents = $this->documents->keyBy('admission_requirement_id');

        return [
            'total' => $requirements->count(),
            'required' => $requirements->where('is_required', true)->count(),
            'uploaded' => $documents->count(),
            'missing_required' => $requirements->where('is_required', true)->reject(fn (AdmissionRequirement $requirement) => $documents->has($requirement->id))->count(),
            'accepted' => $documents->where('status', DocumentStatus::Accepted)->count(),
            'needs_fix' => $documents->where('status', DocumentStatus::NeedsFix)->count(),
        ];
    }

    /**
     * Registrations still missing a required document.
     *
     * @param  Builder<static>  $query
     */
    public function scopeDocumentsIncomplete(Builder $query): void
    {
        $query->whereExists(fn ($sub) => $sub->selectRaw('1')
            ->from('admission_requirements')
            ->whereColumn('admission_requirements.admission_period_id', 'admission_registrations.admission_period_id')
            ->where('admission_requirements.is_required', true)
            ->whereNotExists(fn ($documents) => $documents->selectRaw('1')
                ->from('admission_documents')
                ->whereColumn('admission_documents.admission_requirement_id', 'admission_requirements.id')
                ->whereColumn('admission_documents.admission_registration_id', 'admission_registrations.id')));
    }

    /**
     * Registrations with every required document uploaded and none to fix.
     *
     * @param  Builder<static>  $query
     */
    public function scopeDocumentsComplete(Builder $query): void
    {
        $query->whereNot(fn (Builder $query) => $query->documentsIncomplete())
            ->whereDoesntHave('documents', fn (Builder $query) => $query->where('status', DocumentStatus::NeedsFix->value));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeDocumentsNeedFix(Builder $query): void
    {
        $query->whereHas('documents', fn (Builder $query) => $query->where('status', DocumentStatus::NeedsFix->value));
    }

    /**
     * Personal data is not copied into the audit log, only the fact of a change.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['full_name', 'birth_place', 'birth_date', 'nisn', 'parent_name', 'parent_phone', 'email', 'address'];
    }

    /**
     * Unique, unguessable registration number, e.g. "REG-2610-7KQ4M9".
     */
    protected static function nextNumber(): string
    {
        do {
            $number = 'REG-'.now()->format('ym').'-'.Str::upper(Str::random(6));
        } while (static::query()->withoutGlobalScopes()->where('registration_number', $number)->exists());

        return $number;
    }
}
