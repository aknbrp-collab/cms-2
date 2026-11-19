<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\RegistrationStatus;
use App\Enums\WhatsAppEvent;
use App\Models\AdmissionRegistration;

/**
 * WhatsApp messages to admission guardians: phone formatting, templates
 * and click-to-chat links.
 */
class WhatsApp
{
    /**
     * Placeholders available in templates, with what they stand for.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        return [
            '{wali}' => __('Parent / guardian name'),
            '{nama}' => __('Applicant name'),
            '{nomor}' => __('Registration number'),
            '{status}' => __('Registration status'),
            '{berkas}' => __('List of documents to upload or fix, with notes'),
            '{catatan}' => __('Latest note on a document that needs fixing'),
            '{link}' => __('Link to the "lengkapi berkas" page'),
            '{situs}' => __('Site name'),
        ];
    }

    /**
     * Indonesian number in the international form WhatsApp expects, e.g.
     * "0812-3456 789" or "+62 812 3456 789" → "628123456789". Null when it
     * does not look like a phone number.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^\d{10,15}$/', $digits) ? $digits : null;
    }

    public static function link(string $phone, string $message): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    /**
     * The site's template for an event with the registration filled in.
     */
    public static function message(WhatsAppEvent $event, AdmissionRegistration $registration): string
    {
        $registration->loadMissing(['site', 'period.documentRequirements', 'documents']);
        $site = $registration->site;
        $template = (string) $site->setting($event->settingKey());

        $documents = $registration->documents->keyBy('admission_requirement_id');
        $lines = [];
        foreach ($registration->period->documentRequirements as $requirement) {
            $document = $documents->get($requirement->id);

            if ($document?->status === DocumentStatus::NeedsFix) {
                $lines[] = '- '.$requirement->name.($document->note ? ': '.$document->note : '');
            } elseif ($document === null && $requirement->is_required && $event !== WhatsAppEvent::NeedsFix) {
                $lines[] = '- '.$requirement->name.' (belum diunggah)';
            }
        }

        $latestNote = $registration->documents
            ->where('status', DocumentStatus::NeedsFix)
            ->sortByDesc('reviewed_at')
            ->first()?->note;

        return strtr($template, [
            '{wali}' => $registration->parent_name,
            '{nama}' => $registration->full_name,
            '{nomor}' => $registration->registration_number,
            '{status}' => self::statusLabel($registration->status),
            '{berkas}' => $lines === [] ? '-' : implode("\n", $lines),
            '{catatan}' => (string) $latestNote,
            '{link}' => $site->url('penerimaan/lanjutkan'),
            '{situs}' => $site->name,
        ]);
    }

    /**
     * Status wording for guardians (the public site is in Indonesian).
     */
    public static function statusLabel(RegistrationStatus $status): string
    {
        return match ($status) {
            RegistrationStatus::New => 'Menunggu pemeriksaan berkas',
            RegistrationStatus::Verified => 'Berkas terverifikasi',
            RegistrationStatus::Accepted => 'Diterima',
            RegistrationStatus::Rejected => 'Belum diterima',
        };
    }
}
