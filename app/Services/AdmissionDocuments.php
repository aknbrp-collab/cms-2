<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\WhatsAppEvent;
use App\Models\AdmissionDocument;
use App\Models\AdmissionRegistration;
use App\Models\AdmissionRequirement;
use App\Models\User;
use App\Notifications\AdmissionDocumentNeedsFix;
use App\Services\WhatsApp\WhatsAppNotifier;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Image;
use Throwable;

/**
 * Store applicants' admission documents and record the committee's review.
 */
class AdmissionDocuments
{
    /**
     * Save (or replace) the file for one requirement. The caller has validated
     * the file against the requirement and checked the applicant may upload.
     *
     * @throws ValidationException when an image cannot be processed
     */
    public function store(AdmissionRegistration $registration, AdmissionRequirement $requirement, UploadedFile $file): AdmissionDocument
    {
        $extension = strtolower($file->getClientOriginalExtension()) === 'jpeg' ? 'jpg' : strtolower($file->getClientOriginalExtension());
        $temporary = (string) tempnam(sys_get_temp_dir(), 'adm');
        $path = $temporary.'.'.$extension;
        rename($temporary, $path);
        copy((string) $file->getRealPath(), $path);

        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            $this->reencodeImage($path);
        }

        $document = AdmissionDocument::query()->firstOrNew([
            'admission_registration_id' => $registration->id,
            'admission_requirement_id' => $requirement->id,
        ]);

        $document->forceFill([
            'status' => DocumentStatus::Pending,
            'uploaded_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        $document->addMedia($path)
            ->usingName($requirement->name)
            ->usingFileName(Str::uuid().'.'.$extension)
            ->toMediaCollection('file');

        Audit::record('admission.document_uploaded', $document, meta: ['requirement' => $requirement->name]);

        return $document;
    }

    /**
     * Accept a document, or ask the applicant to replace it (emailed when an
     * address was given).
     */
    public function review(AdmissionDocument $document, DocumentStatus $status, ?string $note, User $reviewer): void
    {
        $document->forceFill([
            'status' => $status,
            'note' => $status === DocumentStatus::NeedsFix ? $note : null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $registration = $document->registration;

        if ($status === DocumentStatus::NeedsFix && filled($registration->email)) {
            Notification::route('mail', $registration->email)->notify(new AdmissionDocumentNeedsFix($document));
        }

        if ($status === DocumentStatus::NeedsFix) {
            app(WhatsAppNotifier::class)->notify(WhatsAppEvent::NeedsFix, $registration->unsetRelation('documents'));
        }
    }

    /**
     * Re-encode an image: applies the EXIF rotation, then drops all metadata
     * (location, camera) and anything hidden after the image data.
     */
    protected function reencodeImage(string $path): void
    {
        try {
            Image::load($path)->orientation()->save($path);
        } catch (Throwable) {
            @unlink($path);

            throw ValidationException::withMessages(['file' => __('Gambar tidak dapat dibaca. Coba simpan ulang atau gunakan format lain.')]);
        }
    }
}
