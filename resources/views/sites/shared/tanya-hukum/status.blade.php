@php use App\Enums\LegalQuestionStatus; @endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Cek Status Pertanyaan')" :crumbs="[__('Tanya Hukum') => route('site.legal-questions.index')]" />

    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 md:py-14">
        @if (session('legal_question_submitted') && $question)
            <div class="mb-8 rounded-2xl border-2 border-dashed border-brand/40 bg-surface-card p-6 text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-mint text-brand"><x-site.icon name="verified" filled class="text-3xl!" /></span>
                <h2 class="mt-4 text-xl font-bold text-on-surface">{{ __('Pertanyaan terkirim') }}</h2>
                <p class="mt-2 text-on-surface-variant">{{ __('Simpan kode berikut untuk mengecek status pertanyaan Anda.') }}</p>
                <p class="mt-3 font-mono text-2xl font-bold tracking-wider text-brand">{{ $question->tracking_code }}</p>
            </div>
        @endif

        <form method="GET" class="flex gap-2">
            <label for="kode" class="sr-only">{{ __('Kode pelacakan') }}</label>
            <input id="kode" name="kode" value="{{ $code }}" placeholder="TH-XXXXXXXX" class="min-w-0 flex-1 rounded-lg border border-border-subtle bg-surface-card px-3 py-2.5 font-mono uppercase shadow-sm focus:border-brand focus:outline-none">
            <button class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-on-primary">{{ __('Cek') }}</button>
        </form>

        @if ($code !== '')
            <div class="mt-8 rounded-xl bg-surface-card p-6 shadow-sm">
                @if ($question)
                    <p class="text-sm text-muted">{{ __('Status') }}</p>
                    <p class="mt-1 text-lg font-semibold text-on-surface">{{ $question->status->publicLabel() }}</p>
                    @if ($question->status === LegalQuestionStatus::Published && $question->slug)
                        <a href="{{ route('site.legal-questions.show', $question->slug) }}" class="mt-4 inline-flex items-center gap-1 font-semibold text-brand">{{ __('Baca jawaban') }} <x-site.icon name="arrow_forward" class="text-[16px]!" /></a>
                    @endif
                @else
                    <p class="text-on-surface-variant">{{ __('Kode tidak ditemukan. Periksa kembali kode pelacakan Anda.') }}</p>
                @endif
            </div>
        @endif
    </div>
</x-site.layout>
