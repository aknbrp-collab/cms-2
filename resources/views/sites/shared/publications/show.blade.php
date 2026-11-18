<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$publication->title" :description="$publication->authors" :crumbs="[__('Publikasi') => route('site.publications.index')]" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 md:py-14 lg:grid-cols-12 lg:px-10">
        <article class="space-y-8 lg:col-span-8">
            @if ($publication->image)
                <figure class="site-card overflow-hidden">
                    <x-site.image :asset="$publication->image" :alt="$publication->image_caption ?: $publication->title" class="aspect-[16/9] w-full" />
                    @if ($publication->image_caption)
                        <figcaption class="px-5 py-3 text-sm text-muted">{{ $publication->image_caption }}</figcaption>
                    @endif
                </figure>
            @endif

            @if ($publication->abstract)
                <section class="site-card p-6">
                    <h2 class="mb-2 text-sm font-bold tracking-wider text-secondary uppercase">{{ __('Abstrak') }}</h2>
                    <p class="leading-relaxed whitespace-pre-line text-on-surface-variant">{{ $publication->abstract }}</p>
                    @if ($publication->keywords)
                        <p class="mt-4 text-sm"><span class="font-semibold text-on-surface">{{ __('Kata kunci:') }}</span> <span class="text-on-surface-variant">{{ $publication->keywords }}</span></p>
                    @endif
                </section>
            @endif

            @if ($publication->body)
                {{-- Sanitised by App\Support\RichText when saved. --}}
                <div class="prose-site">{!! $publication->body !!}</div>
            @endif
        </article>

        <aside class="space-y-6 lg:col-span-4">
            <div class="site-card space-y-3 p-6 lg:sticky lg:top-28">
                <p><span class="text-sm text-muted">{{ __('Jenis') }}</span><br><span class="font-semibold text-on-surface">{{ $publication->type->label() }}</span></p>
                <p><span class="text-sm text-muted">{{ __('Penulis') }}</span><br><span class="font-semibold text-on-surface">{{ $publication->authors }}</span></p>
                <p><span class="text-sm text-muted">{{ __('Sumber') }}</span><br><span class="font-semibold text-on-surface">{{ $publication->citation() }}</span></p>
                @if ($publication->doi)
                    <p><span class="text-sm text-muted">DOI</span><br><a href="https://doi.org/{{ $publication->doi }}" target="_blank" rel="noopener" class="font-semibold break-all text-brand">{{ $publication->doi }}</a></p>
                @endif

                <div class="flex flex-col gap-2 pt-2">
                    @if ($publication->file)
                        <a href="{{ $publication->file->url() }}" target="_blank" rel="noopener" class="site-btn-primary">
                            <x-site.icon name="description" class="text-[18px]!" /> {{ __('Unduh PDF') }}
                        </a>
                    @endif
                    @if ($publication->external_url)
                        <a href="{{ $publication->external_url }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-lg border border-brand px-4 py-2.5 text-sm font-semibold text-brand hover:bg-surface-low">
                            <x-site.icon name="public" class="text-[18px]!" /> {{ __('Baca di sumber') }}
                        </a>
                    @endif
                    @if ($publication->journal)
                        <a href="{{ route('site.journals.show', $publication->journal->slug) }}" class="text-center text-sm text-brand">{{ __('Lihat jurnal :name', ['name' => $publication->journal->name]) }}</a>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</x-site.layout>
