<?php

namespace App\Http\Controllers\Site;

use App\Enums\PublicationType;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\EducationUnit;
use App\Models\Extracurricular;
use App\Models\Facility;
use App\Models\Journal;
use App\Models\Lecturer;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Publication;
use App\Models\Site;
use App\Support\Seo;
use App\Support\SiteTheme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public pages of the site-specific modules: Pondok facilities, achievements
 * and extracurriculars; Ma'had lecturers, programs, publications, journals
 * and organizations.
 */
class DirectoryController extends Controller
{
    public function __construct(protected SiteTheme $theme) {}

    public function facilities(): View
    {
        $site = $this->site('facilities');

        return $this->theme->view('facilities', [
            'seo' => Seo::forSite($site, __('Sarana & Prasarana')),
            'facilities' => Facility::query()->published()->with('image.media')->orderBy('sort_order')->get(),
        ]);
    }

    public function extracurriculars(): View
    {
        $site = $this->site('extracurriculars');

        return $this->theme->view('extracurriculars', [
            'seo' => Seo::forSite($site, __('Ekstrakurikuler')),
            'groups' => Extracurricular::query()->published()->with('image.media')->orderBy('sort_order')->get()
                ->groupBy(fn (Extracurricular $item) => $item->category ?: __('Lainnya')),
        ]);
    }

    public function achievements(): View
    {
        $site = $this->site('achievements');

        return $this->theme->view('achievements', [
            'seo' => Seo::forSite($site, __('Prestasi Santri')),
            'achievements' => Achievement::query()->published()->with('image.media')->orderByDesc('achieved_on')->paginate(18),
        ]);
    }

    public function educationUnits(): View
    {
        $site = $this->site('education_units');

        return $this->theme->view('education-units.index', [
            'seo' => Seo::forSite($site, __('Program Pendidikan')),
            'units' => EducationUnit::query()->published()->with('image.media')->withCount('subjects')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function educationUnit(string $slug): View
    {
        $site = $this->site('education_units');
        $unit = EducationUnit::query()->published()->with(['image.media', 'subjects'])->where('slug', $slug)->firstOrFail();

        return $this->theme->view('education-units.show', [
            'seo' => Seo::forSite($site, $unit->name),
            'unit' => $unit,
            'curriculum' => $unit->curriculum(),
            'others' => EducationUnit::query()->published()->whereKeyNot($unit->id)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function lecturers(Request $request): View
    {
        $site = $this->site('lecturers');
        $search = trim((string) $request->query('q'));

        return $this->theme->view('lecturers.index', [
            'seo' => Seo::forSite($site, __('Direktori Dosen')),
            'search' => $search,
            'lecturers' => Lecturer::query()
                ->published()
                ->with('photo.media')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('expertise', "%{$search}%")))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(24)
                ->withQueryString(),
        ]);
    }

    public function lecturer(string $slug): View
    {
        $site = $this->site('lecturers');
        $lecturer = Lecturer::query()->published()->with('photo.media')->where('slug', $slug)->firstOrFail();

        return $this->theme->view('lecturers.show', [
            'seo' => Seo::forSite($site, $lecturer->name),
            'lecturer' => $lecturer,
            'publications' => $site->hasModule('publications')
                ? Publication::query()->published()->whereLike('authors', '%'.$this->surname($lecturer->name).'%')->latest('year')->limit(10)->get()
                : collect(),
        ]);
    }

    public function programs(): View
    {
        $site = $this->site('programs');

        return $this->theme->view('programs.index', [
            'seo' => Seo::forSite($site, __('Program Takhassus')),
            'programs' => Program::query()->published()->with('image.media')->withCount('courses')->orderBy('sort_order')->get(),
        ]);
    }

    public function program(string $slug): View
    {
        $site = $this->site('programs');
        $program = Program::query()->published()->with(['image.media', 'courses'])->where('slug', $slug)->firstOrFail();

        return $this->theme->view('programs.show', [
            'seo' => Seo::forSite($site, $program->name),
            'program' => $program,
            'semesters' => $program->courses->groupBy('semester')->sortKeys(),
        ]);
    }

    public function publications(Request $request): View
    {
        $site = $this->site('publications');
        $type = PublicationType::tryFrom((string) $request->query('jenis'));
        $year = (int) $request->query('tahun') ?: null;
        $search = trim((string) $request->query('q'));

        return $this->theme->view('publications.index', [
            'seo' => Seo::forSite($site, $type?->label() ?? __('Publikasi & Riset')),
            'type' => $type,
            'year' => $year,
            'search' => $search,
            'years' => Publication::query()->published()->distinct()->orderByDesc('year')->pluck('year'),
            'publications' => Publication::query()
                ->published()
                ->with('journal')
                ->when($type, fn ($query, PublicationType $type) => $query->where('type', $type->value))
                ->when($year, fn ($query, int $year) => $query->where('year', $year))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereLike('title', "%{$search}%")
                    ->orWhereLike('authors', "%{$search}%")
                    ->orWhereLike('keywords', "%{$search}%")))
                ->orderByDesc('year')
                ->latest('published_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function publication(string $slug): View
    {
        $site = $this->site('publications');
        $publication = Publication::query()->published()->with(['journal', 'file.media', 'image.media', 'seo.ogImage.media'])->where('slug', $slug)->firstOrFail();

        return $this->theme->view('publications.show', [
            'seo' => Seo::forContent($site, $publication),
            'publication' => $publication,
        ]);
    }

    public function journals(): View
    {
        $site = $this->site('journals');

        return $this->theme->view('journals.index', [
            'seo' => Seo::forSite($site, __('Jurnal Ilmiah')),
            'journals' => Journal::query()->published()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Journals have no page of their own: old links go to the journal's
     * website, or back to the list when none is set.
     */
    public function journal(string $slug): RedirectResponse
    {
        $this->site('journals');
        $journal = Journal::query()->published()->where('slug', $slug)->firstOrFail();

        return $journal->url
            ? redirect()->away($journal->url, 301)
            : redirect()->route('site.journals.index', status: 301);
    }

    public function organizations(): View
    {
        $site = $this->site('organizations');

        return $this->theme->view('organizations', [
            'seo' => Seo::forSite($site, __('Organisasi Mahasantri')),
            'organizations' => Organization::query()->published()->with('logo.media')->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Last word of a name without academic titles, used to match author lists.
     */
    protected function surname(string $name): string
    {
        $withoutTitles = trim((string) preg_replace('/,.*$/', '', $name));
        $words = preg_split('/\s+/', $withoutTitles) ?: [];

        return (string) end($words);
    }

    protected function site(string $module): Site
    {
        $site = current_site() ?? abort(404);

        abort_unless($site->hasModule($module), 404);

        return $site;
    }
}
