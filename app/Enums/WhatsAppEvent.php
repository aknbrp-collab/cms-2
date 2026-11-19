<?php

namespace App\Enums;

/**
 * Moments the admissions team messages a guardian on WhatsApp. Each has an
 * editable template ("whatsapp.template_{value}" site setting) that falls
 * back to the Indonesian default below.
 */
enum WhatsAppEvent: string
{
    case Received = 'received';
    case Verified = 'verified';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case NeedsFix = 'needs_fix';
    case Reminder = 'reminder';

    public function label(): string
    {
        return match ($this) {
            self::Received => __('Registration received'),
            self::Verified => __('Documents verified'),
            self::Accepted => __('Accepted'),
            self::Rejected => __('Not accepted'),
            self::NeedsFix => __('Document needs fixing'),
            self::Reminder => __('Reminder to complete documents'),
        };
    }

    public function settingKey(): string
    {
        return "whatsapp.template_{$this->value}";
    }

    /**
     * The message suggested after a registration gets this status, if any.
     */
    public static function forStatus(RegistrationStatus $status): ?self
    {
        return match ($status) {
            RegistrationStatus::New => null,
            RegistrationStatus::Verified => self::Verified,
            RegistrationStatus::Accepted => self::Accepted,
            RegistrationStatus::Rejected => self::Rejected,
        };
    }

    public function defaultTemplate(): string
    {
        $greeting = "Assalamu'alaikum warahmatullahi wabarakatuh,\nBapak/Ibu {wali}.\n\n";
        $closing = "\n\nWassalamu'alaikum warahmatullahi wabarakatuh.\nPanitia Penerimaan {situs}";

        return $greeting.match ($this) {
            self::Received => "Pendaftaran *{nama}* di {situs} sudah kami terima dengan nomor pendaftaran *{nomor}*.\n\nSilakan lengkapi berkas pendaftaran melalui tautan berikut (masukkan nomor pendaftaran dan tanggal lahir calon peserta):\n{link}",
            self::Verified => "Berkas pendaftaran *{nama}* (nomor {nomor}) sudah kami periksa dan dinyatakan *lengkap*.\n\nInformasi tahap berikutnya akan kami sampaikan melalui WhatsApp ini.",
            self::Accepted => "Alhamdulillah, dengan senang hati kami sampaikan bahwa *{nama}* (nomor {nomor}) dinyatakan *DITERIMA* di {situs}.\n\nInformasi daftar ulang akan segera kami kirimkan. Barakallahu fiikum.",
            self::Rejected => "Terima kasih atas kepercayaan Bapak/Ibu kepada {situs}.\n\nDengan berat hati kami sampaikan bahwa *{nama}* (nomor {nomor}) belum dapat kami terima pada penerimaan kali ini. Semoga Allah memberikan jalan terbaik.",
            self::NeedsFix => "Berkas pendaftaran *{nama}* (nomor {nomor}) perlu diperbaiki:\n{berkas}\n\nSilakan unggah ulang melalui:\n{link}",
            self::Reminder => "Kami mengingatkan bahwa berkas pendaftaran *{nama}* (nomor {nomor}) belum lengkap:\n{berkas}\n\nSilakan lengkapi melalui:\n{link}",
        }.$closing;
    }
}
