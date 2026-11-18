@php use App\Enums\LegalCategory; @endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Tanya Hukum')" :description="__('Konsultasi hukum Islam yang dijawab oleh dewan asatidz melalui kajian ilmiah.')" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 md:py-14 lg:grid-cols-12 lg:px-10">
        <div class="space-y-6 lg:col-span-8">
            <form method="GET" class="flex flex-wrap gap-2" role="search">
                <label for="q" class="sr-only">{{ __('Cari') }}</label>
                <input id="q" name="q" value="{{ $search }}" placeholder="{{ __('Cari pertanyaan…') }}" class="min-w-0 flex-1 rounded-lg border border-border-subtle bg-surface-card px-3 py-2.5 shadow-sm focus:border-brand focus:outline-none">
                <label for="kategori" class="sr-only">{{ __('Kategori') }}</label>
                <select id="kategori" name="kategori" class="rounded-lg border border-border-subtle bg-surface-card px-3 py-2.5 shadow-sm">
                    <option value="">{{ __('Semua kategori') }}</option>
                    @foreach (LegalCategory::cases() as $case)
                        <option value="{{ $case->value }}" @selected($category === $case)>{{ $case->label() }}</option>
                    @endforeach
                </select>
                <button class="site-btn-primary"><x-site.icon name="search" /></button>
            </form>

            @forelse ($questions as $question)
                <article class="site-card site-card-hover p-5">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full bg-mint px-2.5 py-0.5 font-bold text-brand">{{ $question->category->label() }}</span>
                        <span class="text-muted">{{ $question->published_at?->translatedFormat('d F Y') }}</span>
                    </div>
                    <h2 class="mt-2 text-lg leading-snug font-semibold text-on-surface hover:text-brand">
                        <a href="{{ route('site.legal-questions.show', $question->slug) }}">{{ $question->title }}</a>
                    </h2>
                    <p class="mt-2 line-clamp-2 text-sm text-on-surface-variant">{{ $question->public_question }}</p>
                </article>
            @empty
                <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada tanya jawab yang diterbitkan.') }}</p>
            @endforelse

            <div>{{ $questions->links() }}</div>
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <div class="relative overflow-hidden rounded-2xl bg-primary p-6 text-on-primary shadow-md">
                <div class="absolute -end-8 -top-8 size-32 rounded-full bg-secondary/25"></div>
                <h2 class="relative text-lg font-bold">{{ __('Punya pertanyaan?') }}</h2>
                <p class="relative mt-2 text-sm text-on-primary/80">{{ __('Ajukan pertanyaan seputar hukum Islam. Identitas Anda bisa dirahasiakan.') }}</p>
                <a href="{{ route('site.legal-questions.create') }}" class="relative mt-4 inline-flex items-center gap-2 rounded-lg bg-secondary px-4 py-2 text-sm font-bold text-on-secondary hover:brightness-110">
                    {{ __('Ajukan Pertanyaan') }} <x-site.icon name="arrow_forward" class="text-[16px]!" />
                </a>
            </div>
            <form method="GET" action="{{ route('site.legal-questions.status') }}" class="site-card space-y-3 p-6">
                <h2 class="font-bold text-on-surface">{{ __('Cek status pertanyaan') }}</h2>
                <label for="kode" class="sr-only">{{ __('Kode pelacakan') }}</label>
                <input id="kode" name="kode" placeholder="TH-XXXXXXXX" class="w-full rounded-lg border border-border-subtle px-3 py-2 font-mono uppercase focus:border-brand focus:outline-none">
                <button class="w-full rounded-lg border border-brand px-4 py-2 text-sm font-semibold text-brand hover:bg-surface-low">{{ __('Cek') }}</button>
            </form>
        </aside>
    </div>
</x-site.layout>
