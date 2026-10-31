<?php

namespace App\Http\Controllers\Site;

use App\Enums\LegalCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\AskLegalQuestionRequest;
use App\Models\LegalQuestion;
use App\Models\Site;
use App\Siakad\TanyaHukum;
use App\Support\Seo;
use App\Support\SiteTheme;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public Tanya Hukum: ask a question, track it, read published answers.
 */
class TanyaHukumController extends Controller
{
    public function __construct(protected SiteTheme $theme) {}

    public function index(Request $request): View
    {
        $site = $this->site();
        $category = LegalCategory::tryFrom((string) $request->query('kategori'));
        $search = trim((string) $request->query('q'));

        return $this->theme->view('tanya-hukum.index', [
            'seo' => Seo::forSite($site, __('Tanya Hukum')),
            'category' => $category,
            'search' => $search,
            'questions' => LegalQuestion::query()
                ->published()
                ->when($category, fn ($query, LegalCategory $category) => $query->where('category', $category->value))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereLike('title', "%{$search}%")
                    ->orWhereLike('public_question', "%{$search}%")))
                ->latest('published_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function show(string $slug): View
    {
        $site = $this->site();
        $question = LegalQuestion::query()->published()->where('slug', $slug)->firstOrFail();

        return $this->theme->view('tanya-hukum.show', [
            'seo' => new Seo(
                title: "{$question->title} | Tanya Hukum | {$site->name}",
                description: Str::limit((string) $question->public_question, 160),
                image: $site->brandingUrl('og_default'),
                canonical: url()->current(),
                type: 'article',
            ),
            'question' => $question,
            'related' => LegalQuestion::query()->published()->where('category', $question->category->value)->whereKeyNot($question->id)->latest('published_at')->limit(4)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $site = $this->site();
        $request->session()->put(AskLegalQuestionRequest::SESSION_KEY, now()->getTimestamp());

        return $this->theme->view('tanya-hukum.ask', [
            'seo' => new Seo(__('Ajukan Pertanyaan').' | Tanya Hukum | '.$site->name, canonical: url()->current()),
        ]);
    }

    public function store(AskLegalQuestionRequest $request, TanyaHukum $tanyaHukum): RedirectResponse
    {
        $site = $this->site();

        $question = LegalQuestion::query()->create([
            ...$request->safe()->only(['asker_name', 'asker_city', 'asker_email', 'category', 'question', 'is_anonymous']),
            'site_id' => $site->id,
            'ip_address' => $request->ip(),
        ]);

        $request->session()->forget(AskLegalQuestionRequest::SESSION_KEY);
        $tanyaHukum->notifyNew($question);

        return redirect()->route('site.legal-questions.status', ['kode' => $question->tracking_code])
            ->with('legal_question_submitted', true);
    }

    /**
     * Status lookup by tracking code. Shows the status only, never the question.
     */
    public function status(Request $request): View
    {
        $site = $this->site();
        $code = Str::upper(trim((string) $request->query('kode')));

        return $this->theme->view('tanya-hukum.status', [
            'seo' => new Seo(__('Cek Status Pertanyaan').' | '.$site->name, canonical: route('site.legal-questions.status'), noindex: true),
            'code' => $code,
            'question' => $code !== '' ? LegalQuestion::query()->where('tracking_code', $code)->first() : null,
        ]);
    }

    protected function site(): Site
    {
        $site = current_site() ?? abort(404);

        abort_unless($site->hasModule('legal_questions'), 404);

        return $site;
    }
}
