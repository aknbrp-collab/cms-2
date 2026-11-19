<?php

namespace App\Services;

use App\Models\AdmissionDocument;
use App\Models\AdmissionMessage;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRegistration;
use App\Models\IntegrationLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Permanent clean-up from the admin Data page (both sites):
 *
 * - "admissions": registrations of admission periods that closed before a
 *   date, with their uploaded documents (files included) and WhatsApp
 *   messages. The periods themselves are kept.
 * - "messages": WhatsApp message history sent before a date.
 * - "integration_logs": SIAKAD/social integration logs before a date.
 *
 * Audit logs are append-only and never purged.
 */
class DataPurge
{
    public const KINDS = ['admissions', 'messages', 'integration_logs'];

    /**
     * What would be deleted.
     *
     * @return array{total: int, details: array<string, int>, periods: list<array{title: string, site: string, closes_on: string, registrations: int}>}
     */
    public function preview(string $kind, CarbonInterface $before): array
    {
        return match ($kind) {
            'admissions' => $this->admissionsPreview($before),
            'messages' => ['total' => $this->messages($before)->count(), 'details' => [], 'periods' => []],
            'integration_logs' => ['total' => $this->integrationLogs($before)->count(), 'details' => [], 'periods' => []],
            default => throw new InvalidArgumentException("Unknown purge kind {$kind}"),
        };
    }

    /**
     * Delete it. Returns the counts deleted.
     *
     * @return array<string, int>
     */
    public function purge(string $kind, CarbonInterface $before): array
    {
        return match ($kind) {
            'admissions' => $this->purgeAdmissions($before),
            'messages' => ['messages' => $this->messages($before)->delete()],
            'integration_logs' => ['integration_logs' => $this->integrationLogs($before)->delete()],
            default => throw new InvalidArgumentException("Unknown purge kind {$kind}"),
        };
    }

    /**
     * @return array{total: int, details: array<string, int>, periods: list<array{title: string, site: string, closes_on: string, registrations: int}>}
     */
    protected function admissionsPreview(CarbonInterface $before): array
    {
        $periods = AdmissionPeriod::withoutSiteScope()
            ->with('site')
            ->whereDate('closes_on', '<', $before)
            ->withCount(['registrations' => fn (Builder $query) => $query->withoutGlobalScope('site')])
            ->orderBy('closes_on')
            ->get();

        $registrations = $this->registrations($before);
        $list = [];

        foreach ($periods as $period) {
            $count = (int) $period->getAttribute('registrations_count');

            if ($count > 0) {
                $list[] = [
                    'title' => trim($period->title.' '.$period->academic_year),
                    'site' => $period->site->name,
                    'closes_on' => $period->closes_on->toDateString(),
                    'registrations' => $count,
                ];
            }
        }

        return [
            'total' => (clone $registrations)->count(),
            'details' => [
                'documents' => AdmissionDocument::query()->whereIn('admission_registration_id', (clone $registrations)->select('id'))->count(),
                'messages' => AdmissionMessage::query()->whereIn('admission_registration_id', (clone $registrations)->select('id'))->count(),
            ],
            'periods' => $list,
        ];
    }

    /**
     * Deleted one by one through the models, so uploaded files are removed
     * from storage and the audit log notes each deletion (without the
     * personal fields, see AdmissionRegistration::auditExclude()).
     *
     * @return array<string, int>
     */
    protected function purgeAdmissions(CarbonInterface $before): array
    {
        $counts = ['registrations' => 0, 'documents' => 0, 'messages' => 0];

        $this->registrations($before)->with('documents.media')->chunkById(100, function ($registrations) use (&$counts) {
            foreach ($registrations as $registration) {
                foreach ($registration->documents as $document) {
                    $document->delete();
                    $counts['documents']++;
                }

                $counts['messages'] += $registration->messages()->delete();
                $registration->delete();
                $counts['registrations']++;
            }
        });

        return $counts;
    }

    /**
     * @return Builder<AdmissionRegistration>
     */
    protected function registrations(CarbonInterface $before): Builder
    {
        return AdmissionRegistration::withoutSiteScope()
            ->whereIn('admission_period_id', AdmissionPeriod::withoutSiteScope()->whereDate('closes_on', '<', $before)->select('id'));
    }

    /**
     * @return Builder<AdmissionMessage>
     */
    protected function messages(CarbonInterface $before): Builder
    {
        return AdmissionMessage::query()->where('created_at', '<', $before);
    }

    /**
     * @return Builder<IntegrationLog>
     */
    protected function integrationLogs(CarbonInterface $before): Builder
    {
        return IntegrationLog::query()->where('created_at', '<', $before);
    }
}
