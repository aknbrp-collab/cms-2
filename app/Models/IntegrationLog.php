<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One call between the CMS and an external system (SIAKAD).
 *
 * @property string $id
 * @property string $system
 * @property string $direction inbound|outbound
 * @property string $action
 * @property string $status success|failed|rejected
 * @property int|null $http_status
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $external_id
 * @property array<string, mixed>|null $request
 * @property array<string, mixed>|null $response
 * @property string|null $error
 * @property int|null $duration_ms
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'system', 'direction', 'action', 'status', 'http_status', 'subject_type', 'subject_id',
    'external_id', 'request', 'response', 'error', 'duration_ms', 'ip_address',
])]
class IntegrationLog extends Model
{
    use HasUuids;

    public const INBOUND = 'inbound';

    public const OUTBOUND = 'outbound';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    /** Inbound request refused (bad signature, invalid payload). */
    public const REJECTED = 'rejected';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request' => 'array',
            'response' => 'array',
            'http_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRetryable(): bool
    {
        return $this->direction === self::OUTBOUND && $this->status === self::FAILED && $this->subject_id !== null;
    }
}
