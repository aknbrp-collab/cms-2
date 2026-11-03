<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\AdmissionRegistration;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download admission registrations as CSV (Excel-friendly, UTF-8 with BOM).
 */
class ExportRegistrationsController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = AdmissionRegistration::query()
            ->with(['period.documentRequirements', 'documents'])
            ->whereIn('site_id', $user->accessibleSites()->modelKeys())
            ->when($request->string('period')->toString(), fn ($query, string $period) => $query->where('admission_period_id', $period))
            ->when(RegistrationStatus::tryFrom($request->string('status')->toString()), fn ($query, RegistrationStatus $status) => $query->where('status', $status->value))
            ->oldest();

        Audit::record('admissions.exported', meta: $request->only('period', 'status'));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Nomor', 'Periode', 'Status', 'Nama Lengkap', 'L/P', 'Tempat Lahir', 'Tanggal Lahir', 'NISN',
                'Asal Sekolah', 'Program', 'Nama Wali', 'No. HP Wali', 'Email', 'Alamat', 'Berkas', 'Catatan', 'Tanggal Daftar',
            ]);

            $query->chunk(500, function ($registrations) use ($out) {
                foreach ($registrations as $registration) {
                    fputcsv($out, array_map([$this, 'safeCell'], [
                        $registration->registration_number,
                        $registration->period->title.' '.$registration->period->academic_year,
                        $registration->status->label(),
                        $registration->full_name,
                        $registration->gender,
                        $registration->birth_place,
                        $registration->birth_date->toDateString(),
                        (string) $registration->nisn,
                        $registration->previous_school,
                        (string) $registration->program,
                        $registration->parent_name,
                        $registration->parent_phone,
                        (string) $registration->email,
                        $registration->address,
                        $this->documentsCell($registration->documentSummary()),
                        (string) $registration->admin_notes,
                        (string) $registration->created_at?->toDateTimeString(),
                    ]));
                }
            });

            fclose($out);
        }, 'pendaftaran-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * E.g. "3/5 diunggah, 2 diterima, 1 perlu perbaikan".
     *
     * @param  array{total: int, required: int, uploaded: int, missing_required: int, accepted: int, needs_fix: int}  $summary
     */
    protected function documentsCell(array $summary): string
    {
        if ($summary['total'] === 0) {
            return '';
        }

        return "{$summary['uploaded']}/{$summary['total']} diunggah, {$summary['accepted']} diterima, {$summary['needs_fix']} perlu perbaikan";
    }

    /**
     * Prevent spreadsheet formula injection from user-submitted values.
     */
    public function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
