<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$question->title" :crumbs="[__('Tanya Hukum') => route('site.legal-questions.index')]" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 md:py-14 lg:grid-cols-12 lg:px-10">
        <article class="space-y-8 lg:col-span-8">
            <section class="site-card border-s-4 border-secondary p-6">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-mint px-2.5 py-0.5 font-bold text-brand">{{ $question->category->label() }}</span>
                    <span class="text-muted">{{ __('Ditanyakan oleh :name', ['name' => $question->publicAskerName()]) }}</span>
                </div>
                <h2 class="mb-2 text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Pertanyaan') }}</h2>
                <p class="leading-relaxed whitespace-pre-line text-on-surface">{{ $question->public_question }}</p>
            </section>

            <section>
                <h2 class="mb-3 text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Jawaban') }}</h2>
                {{-- Sanitised by App\Support\RichText when received and when published. --}}
                <div class="prose-site">{!! $question->answer !!}</div>

                @if ($question->answer_references)
                    <div class="mt-6 rounded-xl bg-surface-low p-4 text-sm">
                        <p class="font-semibold text-on-surface">{{ __('Rujukan') }}</p>
                        <p class="mt-1 whitespace-pre-line text-on-surface-variant">{{ $question->answer_references }}</p>
                    </div>
                @endif

                <p class="mt-6 text-sm text-muted">
                    {{ __('Dijawab oleh :name', ['name' => $question->answered_by]) }} ·
                    {{ __('Diterbitkan :date', ['date' => $question->published_at?->translatedFormat('d F Y')]) }}
                </p>
            </section>
        </article>

        <aside class="space-y-6 lg:col-span-4">
            <a href="{{ route('site.legal-questions.create') }}" class="site-btn-primary py-3">
                {{ __('Ajukan Pertanyaan') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
            </a>
            @if ($related->isNotEmpty())
                <div class="site-card p-6">
                    <h2 class="mb-3 font-bold text-on-surface">{{ __('Pertanyaan terkait') }}</h2>
                    <ul class="space-y-3">
                        @foreach ($related as $item)
                            <li><a href="{{ route('site.legal-questions.show', $item->slug) }}" class="text-sm font-semibold text-on-surface hover:text-brand">{{ $item->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-site.layout>
