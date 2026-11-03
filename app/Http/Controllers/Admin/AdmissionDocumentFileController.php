<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionDocument;
use App\Models\AdmissionRegistration;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve an applicant's uploaded document to admission staff. Images open
 * inline for review; other files (PDF) always download. Downloads are audited.
 */
class AdmissionDocumentFileController extends Controller
{
    public function __invoke(Request $request, AdmissionDocument $document): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();

        $registration = AdmissionRegistration::query()
            ->whereIn('site_id', $user->accessibleSites()->modelKeys())
            ->findOrFail($document->admission_registration_id);

        $media = $document->file() ?? abort(404);
        $download = $request->boolean('download') || ! $document->isImage();
        $filename = Str::slug($document->requirement->name).'-'.$registration->registration_number.'.'.$media->extension;

        if ($download) {
            Audit::record('admission.document_downloaded', $document, meta: ['registration' => $registration->registration_number]);
        }

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
