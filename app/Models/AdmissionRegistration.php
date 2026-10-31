<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
