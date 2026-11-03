<?php

namespace App\Notifications;

use App\Models\AdmissionDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ask an applicant (by email, when given) to replace a document. Written in
 * Indonesian like the public site.
 */
class AdmissionDocumentNeedsFix extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AdmissionDocument $document) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $registration = $this->document->registration;
        $site = $registration->site;

        return (new MailMessage)
            ->subject('Berkas pendaftaran perlu diperbaiki: '.$this->document->requirement->name)
            ->greeting('Assalamu\'alaikum, '.$registration->parent_name.',')
            ->line('Panitia penerimaan '.$site->name.' telah memeriksa berkas pendaftaran '.$registration->full_name.' (nomor '.$registration->registration_number.').')
            ->line('Berkas "'.$this->document->requirement->name.'" perlu diunggah ulang.')
            ->line('Catatan panitia: '.($this->document->note ?: '-'))
            ->action('Lengkapi berkas', $site->url('penerimaan/lanjutkan'))
            ->line('Masukkan nomor pendaftaran dan tanggal lahir calon peserta untuk membuka halaman berkas.')
            ->salutation('Wassalam, Panitia Penerimaan '.$site->name);
    }
}
