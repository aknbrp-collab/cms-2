@php
    use App\Enums\PageTemplate;

    $crumbs = $page->parent ? [$page->parent->title => route('site.pages.show', $page->parent->slug)] : [];
    $hasSidebar = $page->template === PageTemplate::Contact || $siblings->count() > 1;
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$page->title" :crumbs="$crumbs" />

    <div @class([
        'mx-auto px-4 py-10 sm:px-6 md:py-14 lg:px-10',
        'max-w-7xl' => $page->template === PageTemplate::FullWidth || $hasSidebar,
        'max-w-4xl' => $page->template !== PageTemplate::FullWidth && ! $hasSidebar,
    ])>
        <div @class(['grid gap-10 lg:grid-cols-12' => $hasSidebar])>
            <div @class(['space-y-8', 'lg:col-span-8' => $hasSidebar])>
                @include('sites.shared.blocks', ['blocks' => $page->blocks ?? []])
            </div>

            @if ($hasSidebar)
                <aside class="space-y-6 lg:col-span-4">
                    @if ($page->template === PageTemplate::Contact)
                        @include('sites.shared.contact-card')
                    @endif

                    @if ($siblings->count() > 1)
                        <nav class="site-card p-5" aria-label="{{ $page->parent?->title ?? $page->title }}">
                            <h2 class="mb-3 border-b-2 border-brand pb-1 text-base font-semibold text-on-surface">{{ $page->parent?->title ?? $page->title }}</h2>
                            <ul class="space-y-1">
                                @foreach ($siblings as $sibling)
                                    <li>
                                        <a href="{{ route('site.pages.show', $sibling->slug) }}" @class([
                                            'flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm transition',
                                            'bg-primary text-on-primary font-semibold' => $sibling->is($page),
                                            'text-on-surface-variant hover:bg-surface-low hover:text-brand' => ! $sibling->is($page),
                                        ])>
                                            <x-site.icon name="chevron_right" class="text-[16px]!" /> {{ $sibling->title }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    @endif
                </aside>
            @endif
        </div>
    </div>
</x-site.layout>
