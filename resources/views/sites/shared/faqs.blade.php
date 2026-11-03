<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Tanya Jawab (FAQ)')" :description="__('Jawaban atas pertanyaan yang sering diajukan.')" />

    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 md:py-14">
        @forelse ($groups as $group => $faqs)
            <section class="mb-10">
                <h2 class="mb-4 text-xl font-bold text-brand">{{ $group }}</h2>
                @include('sites.shared.faq-list', ['faqs' => $faqs])
            </section>
        @empty
            <p class="rounded-xl bg-surface-card p-10 text-center text-on-surface-variant shadow-sm">{{ __('Belum ada pertanyaan.') }}</p>
        @endforelse
    </div>
</x-site.layout>
