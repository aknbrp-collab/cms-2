<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToSite;
use App\Concerns\HasPublishToggle;
use Database\Factories\AdmissionPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One admission wave (PSB/PMB), e.g. "Gelombang II 2026/2027".
 *
 * @property string $id
 * @property string $site_id
 * @property string $title
 * @property string $academic_year
 * @property Carbon $opens_on
 * @property Carbon $closes_on
 * @property string|null $summary
 * @property string|null $requirements
 * @property string|null $fees
 * @property list<array{title: string, description: string}>|null $steps
 * @property list<string>|null $programs
 * @property string|null $brochure_id
 * @property bool $accepts_online_registration
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id', 'title', 'academic_year', 'opens_on', 'closes_on', 'summary', 'requirements', 'fees',
    'steps', 'programs', 'brochure_id', 'accepts_online_registration',
])]
class AdmissionPeriod extends Model
{
    /** @use HasFactory<AdmissionPeriodFactory> */
    use Auditable, BelongsToSite, HasFactory, HasPublishToggle, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'accepts_online_registration' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_on' => 'date',
            'closes_on' => 'date',
            'steps' => 'array',
            'programs' => 'array',
            'accepts_online_registration' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function brochure(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'brochure_id');
    }

    /**
     * @return HasMany<AdmissionRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(AdmissionRegistration::class);
    }

    /**
     * @return HasMany<AdmissionRequirement, $this>
     */
    public function documentRequirements(): HasMany
    {
        return $this->hasMany(AdmissionRequirement::class)->orderBy('sort_order');
    }

    /**
     * Published periods whose registration window includes today.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_published', true)
            ->whereDate('opens_on', '<=', today())
            ->whereDate('closes_on', '>=', today());
    }

    public function isOpen(): bool
    {
        return $this->is_published && today()->between($this->opens_on, $this->closes_on);
    }

    public function acceptsRegistrations(): bool
    {
        return $this->accepts_online_registration && $this->isOpen();
    }
}
