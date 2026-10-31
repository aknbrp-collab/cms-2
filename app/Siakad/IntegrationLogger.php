<?php

namespace App\Siakad;

use App\Models\IntegrationLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Write SIAKAD calls to the integration log (the "API sync attempts" audit trail).
 */
class IntegrationLogger
{
    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    public static function log(
        string $direction,
        string $action,
        string $status,
        ?Model $subject = null,
        ?string $externalId = null,
        ?array $request = null,
        ?array $response = null,
        ?int $httpStatus = null,
        ?string $error = null,
        ?int $durationMs = null,
    ): IntegrationLog {
        return IntegrationLog::query()->create([
            'system' => 'siakad',
            'direction' => $direction,
            'action' => $action,
            'status' => $status,
            'http_status' => $httpStatus,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'external_id' => $externalId,
            'request' => $request,
            'response' => $response,
            'error' => $error,
            'duration_ms' => $durationMs,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
