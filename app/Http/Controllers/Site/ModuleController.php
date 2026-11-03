<?php

namespace App\Http\Controllers\Site;

use App\Enums\GalleryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\RegisterAdmissionRequest;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionRegistration;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\Leader;
use App\Models\Site;
use App\Support\AdmissionAccess;
use App\Support\Seo;
use App\Support\SiteTheme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Public pages of the site modules: agenda, galleries, FAQ, leadership, admissions.
 */
class ModuleController extends Controller
{
    public function __construct(protected SiteTheme $theme) {}

    public function events(Request $request): View
    {
        $site = $this->site('events');
        $past = $request->query('arsip') === '1';

        return $this->theme->view('events.index', [
            'seo' => Seo::forSite($site, __('Agenda')),
            'past' => $past,
            'events' => Event::query()
                ->published()
                ->with('featuredImage.media')
                ->when(
                    $past,
                    fn ($query) => $query->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at'),
                    fn ($query) => $query->upcoming()->orderBy('starts_at'),
                )
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function event(string $slug): View
    {
        $site = $this->site('events');
        $event = Event::query()->published()->with(['featuredImage.media', 'seo.ogImage.media'])->where('slug', $slug)->firstOrFail();

        return $this->theme->view('events.show', [
            'seo' => Seo::forContent($site, $event),
            'event' => $event,
        ]);
    }

    public function galleries(Request $request): View
    {
        $site = $this->site('galleries');
        $type = GalleryType::tryFrom((string) $request->query('jenis'));

        return $this->theme->view('galleries.index', [
            'seo' => Seo::forSite($site, $type === GalleryType::Video ? __('Video') : __('Galeri')),
            'type' => $type,
            'galleries' => Gallery::query()
                ->published()
                ->with(['coverImage.media', 'items.mediaAsset.media'])
                ->when($type, fn ($query, GalleryType $type) => $query->where('type', $type->value))
                ->latest('published_at')
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function gallery(string $slug): View
    {
        $site = $this->site('galleries');
        $gallery = Gallery::query()->published()->with(['items.mediaAsset.media', 'seo.ogImage.media', 'coverImage.media'])->where('slug', $slug)->firstOrFail();

        return $this->theme->view('galleries.show', [
            'seo' => Seo::forContent($site, $gallery),
            'gallery' => $gallery,
        ]);
    }

    public function faqs(): View
    {
        $site = $this->site('faqs');

        return $this->theme->view('faqs', [
            'seo' => Seo::forSite($site, __('Tanya Jawab (FAQ)')),
            'groups' => Faq::query()->published()->orderBy('sort_order')->get()->groupBy(fn (Faq $faq) => $faq->group ?: __('Umum')),
        ]);
    }

    public function leaders(): View
    {
        $site = $this->site('leaders');

        return $this->theme->view('leaders', [
            'seo' => Seo::forSite($site, __('Pimpinan')),
            'leaders' => Leader::query()->published()->with('photo.media')->orderBy('sort_order')->get(),
        ]);
    }

    public function admissions(): View
    {
        $site = $this->site('admissions');
        $period = $this->currentPeriod();

        return $this->theme->view('admissions.show', [
            'seo' => Seo::forSite($site, $period ? __('Penerimaan :year', ['year' => $period->academic_year]) : __('Penerimaan')),
            'period' => $period,
            'faqs' => $site->hasModule('faqs')
                ? Faq::query()->published()->whereIn('group', ['Pendaftaran', 'PSB', 'PMB'])->orderBy('sort_order')->get()
                : collect(),
        ]);
    }

    public function registerForm(): View
    {
        $site = $this->site('admissions');
        $period = $this->currentPeriod();

        abort_unless($period?->acceptsRegistrations(), 404);

        session(['registration_form_started' => now()->timestamp]);

        return $this->theme->view('admissions.register', [
            'seo' => new Seo(__('Formulir Pendaftaran').' | '.$site->name, canonical: url()->current(), noindex: true),
            'period' => $period,
        ]);
    }

    public function register(RegisterAdmissionRequest $request): RedirectResponse
    {
        $site = $this->site('admissions');
        $period = $request->period() ?? abort(404);

        $registration = AdmissionRegistration::query()->create([
            ...$request->safe()->except(['website', 'agree']),
            'site_id' => $site->id,
            'admission_period_id' => $period->id,
            'ip_address' => $request->ip(),
        ]);

        $request->session()->forget('registration_form_started');

        if ($period->documentRequirements()->exists()) {
            AdmissionAccess::grant($registration);

            return redirect()->route('site.admissions.documents');
        }

        return redirect()->to(URL::temporarySignedRoute(
            'site.admissions.done',
            now()->addDay(),
            ['number' => $registration->registration_number],
        ));
    }

    public function registered(string $number): View
    {
        $site = $this->site('admissions');
        $registration = AdmissionRegistration::query()->with('period')->where('registration_number', $number)->firstOrFail();

        return $this->theme->view('admissions.done', [
            'seo' => new Seo(__('Pendaftaran berhasil').' | '.$site->name, canonical: url()->current(), noindex: true),
            'registration' => $registration,
        ]);
    }

    /**
     * The open admission period, or the most recent published one.
     */
    protected function currentPeriod(): ?AdmissionPeriod
    {
        return AdmissionPeriod::query()->open()->with('brochure.media')->orderBy('closes_on')->first()
            ?? AdmissionPeriod::query()->published()->with('brochure.media')->latest('opens_on')->first();
    }

    protected function site(string $module): Site
    {
        $site = current_site() ?? abort(404);

        abort_unless($site->hasModule($module), 404);

        return $site;
    }
}
