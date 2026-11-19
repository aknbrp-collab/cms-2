<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Testimoni Alumni')" :description="__('Cerita dan kesan para alumni.')" :crumbs="[__('Alumni') => route($site->hasModule('alumni') ? 'site.alumni.index' : 'site.alumni.testimonials')]" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        @if ($testimonials->isEmpty())
            <p class="site-card p-10 text-center text-on-surface-variant">{{ __('Belum ada testimoni.') }}</p>
        @else
            <div class="grid items-stretch gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <figure class="site-card site-accent-top flex h-full flex-col gap-5 p-6">
                        <x-site.icon name="format_quote" filled class="text-4xl! text-brand opacity-40" />
                        <blockquote class="flex-1 text-base leading-[26px] text-pretty text-on-surface">{{ $testimonial->quote }}</blockquote>
                        <figcaption class="flex items-center gap-4 border-t border-border-subtle pt-5">
                            <x-site.image :asset="$testimonial->photo" conversion="thumb" :alt="$testimonial->name" icon="person" class="size-14 shrink-0 rounded-full object-top" />
                            <div class="min-w-0">
                                <p class="font-bold text-on-surface">{{ $testimonial->name }}</p>
                                @if ($testimonial->graduation_year)
                                    <p class="site-meta text-xs!">{{ __('Alumni :year', ['year' => $testimonial->graduation_year]) }}</p>
                                @endif
                                @if ($testimonial->occupation)
                                    <p class="text-sm text-on-surface-variant">{{ $testimonial->occupation }}</p>
                                @endif
                            </div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</x-site.layout>
