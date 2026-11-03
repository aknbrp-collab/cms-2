<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AdmissionDocument;
use App\Models\AdmissionRegistration;
use App\Models\Site;
use App\Services\AdmissionDocuments;
use App\Support\AdmissionAccess;
use App\Support\Seo;
use App\Support\SiteTheme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Step 2 of online admission: applicants upload the documents their period
 * asks for, right after the form or later with number + birth date.
 */
class AdmissionDocumentController extends Controller
{
    public function __construct(protected SiteTheme $theme) {}

    public function index(): View|RedirectResponse
    {
        $site = $this->site();
        $registration = AdmissionAccess::registration();

        if ($registration === null) {
            return redirect()->route('site.admissions.access');
        }

        $documents = $registration->documents->keyBy('admission_requirement_id');

        return $this->theme->view('admissions.documents', [
            'seo' => new Seo(__('Unggah Berkas').' | '.$site->name, canonical: url()->current(), noindex: true),
            'registration' => $registration,
            'requirements' => $registration->period->documentRequirements,
            'documents' => $documents,
            'summary' => $registration->documentSummary(),
            'finishUrl' => URL::temporarySignedRoute('site.admissions.done', now()->addDay(), ['number' => $registration->registration_number]),
        ]);
    }

    public function upload(Request $request, string $requirement, AdmissionDocuments $documents): RedirectResponse|JsonResponse
    {
        $this->site();
        $registration = AdmissionAccess::registration() ?? abort(403);
        $requirement = $registration->period->documentRequirements->firstWhere('id', $requirement) ?? abort(404);
        $existing = $registration->documents->firstWhere('admission_requirement_id', $requirement->id);
        $bag = 'upload_'.$requirement->id;

        try {
            if (! AdmissionDocument::applicantCanUpload($registration, $existing)) {
                throw ValidationException::withMessages(['file' => __('Berkas ini tidak dapat diubah lagi.')]);
            }

            $request->validate(['file' => $requirement->uploadRules()], [], ['file' => __('berkas')]);
            $documents->store($registration, $requirement, $request->file('file'));
        } catch (ValidationException $exception) {
            throw $exception->errorBag($bag)->redirectTo(route('site.admissions.documents').'#berkas-'.$requirement->id);
        }

        // Flashed for both paths: the JavaScript upload reloads the page afterwards.
        $request->session()->flash('uploaded', __(':name berhasil diunggah.', ['name' => $requirement->name]));

        return $request->wantsJson()
            ? response()->json(['ok' => true])
            : redirect()->to(route('site.admissions.documents').'#berkas-'.$requirement->id);
    }

    public function accessForm(): View
    {
        $site = $this->site();

        return $this->theme->view('admissions.access', [
            'seo' => new Seo(__('Lengkapi Berkas').' | '.$site->name, canonical: url()->current(), noindex: true),
        ]);
    }

    public function access(Request $request): RedirectResponse
    {
        $this->site();

        $data = $request->validate([
            'registration_number' => ['required', 'string', 'max:30'],
            'birth_date' => ['required', 'date'],
        ], [], [
            'registration_number' => __('nomor pendaftaran'),
            'birth_date' => __('tanggal lahir'),
        ]);

        // The site scope limits the lookup to the site being visited.
        $registration = AdmissionRegistration::query()
            ->where('registration_number', strtoupper(trim($data['registration_number'])))
            ->whereDate('birth_date', $data['birth_date'])
            ->first();

        if ($registration === null) {
            throw ValidationException::withMessages(['registration_number' => __('Nomor pendaftaran atau tanggal lahir tidak cocok.')]);
        }

        $request->session()->regenerate();
        AdmissionAccess::grant($registration);

        return redirect()->route('site.admissions.documents');
    }

    protected function site(): Site
    {
        $site = current_site() ?? abort(404);

        abort_unless($site->hasModule('admissions'), 404);

        return $site;
    }
}
