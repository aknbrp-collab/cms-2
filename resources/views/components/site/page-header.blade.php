@props(['title', 'description' => null, 'crumbs' => []])

{{-- Title band with breadcrumbs at the top of inner pages. Styled per theme (site-band). --}}
<section class="site-band">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-10 sm:px-6 md:py-12 lg:px-10">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-xs font-bold opacity-75">
            <a href="{{ route('site.home') }}" class="hover:underline">{{ __('Beranda') }}</a>
            @foreach ($crumbs as $label => $url)
                <span aria-hidden="true">/</span>
                <a href="{{ $url }}" class="hover:underline">{{ $label }}</a>
            @endforeach
            <span aria-hidden="true">/</span>
            <span aria-current="page" class="truncate">{{ $title }}</span>
        </nav>
        <h1 class="site-band-title max-w-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="max-w-3xl text-base leading-relaxed opacity-80">{{ $description }}</p>
        @endif
    </div>
</section>
