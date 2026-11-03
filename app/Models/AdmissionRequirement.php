<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A document an admission period asks applicants to upload, e.g. "Kartu Keluarga".
 *
 * @property string $id
 * @property string $admission_period_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_required
 * @property list<string> $file_types keys of FILE_TYPES
 * @property int $max_size_kb
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['admission_period_id', 'name', 'description', 'is_required', 'file_types', 'max_size_kb', 'sort_order'])]
class AdmissionRequirement extends Model
{
    use Auditable, HasUuids;

    /**
     * Allowed upload types: key => [extensions, MIME types detected from the content].
     *
     * @var array<string, array{extensions: list<string>, mimes: list<string>, label: string}>
     */
    public const FILE_TYPES = [
        'pdf' => ['extensions' => ['pdf'], 'mimes' => ['application/pdf'], 'label' => 'PDF'],
        'jpg' => ['extensions' => ['jpg', 'jpeg'], 'mimes' => ['image/jpeg'], 'label' => 'JPG'],
        'png' => ['extensions' => ['png'], 'mimes' => ['image/png'], 'label' => 'PNG'],
        'webp' => ['extensions' => ['webp'], 'mimes' => ['image/webp'], 'label' => 'WEBP'],
    ];

    public const DEFAULT_MAX_SIZE_KB = 5120;

    /** Bound by the 12 MB upload limit of the server and media library. */
    public const MAX_SIZE_KB = 12288;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_required' => true,
        'max_size_kb' => self::DEFAULT_MAX_SIZE_KB,
        'sort_order' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'file_types' => 'array',
            'max_size_kb' => 'integer',
            'sort_order' => 'integer',
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
     * @return list<string>
     */
    public function extensions(): array
    {
        return array_merge(...array_map(fn (string $type) => self::FILE_TYPES[$type]['extensions'] ?? [], $this->file_types));
    }

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return array_merge(...array_map(fn (string $type) => self::FILE_TYPES[$type]['mimes'] ?? [], $this->file_types));
    }

    /**
     * Human-readable limits, e.g. "PDF, JPG · maks. 5 MB".
     */
    public function limitsLabel(): string
    {
        $types = implode(', ', array_map(fn (string $type) => self::FILE_TYPES[$type]['label'] ?? strtoupper($type), $this->file_types));

        return __(':types · maks. :size MB', ['types' => $types, 'size' => round($this->max_size_kb / 1024, 1)]);
    }

    /**
     * Upload rules for this requirement. The MIME check reads the file content.
     *
     * @return list<string>
     */
    public function uploadRules(): array
    {
        return [
            'required',
            'file',
            'max:'.min($this->max_size_kb, self::MAX_SIZE_KB),
            'extensions:'.implode(',', $this->extensions()),
            'mimetypes:'.implode(',', $this->mimeTypes()),
        ];
    }
}
