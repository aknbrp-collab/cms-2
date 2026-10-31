@props(['title', 'description' => null, 'crumbs' => []])

{{-- Title band with breadcrumbs at the top of inner pages. --}}
<section class="bg-primary text-on-primary">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-10">
        <nav aria-label="Breadcrumb" class="mb-3 flex flex-wrap items-center gap-1 text-sm text-on-primary/70">
            <a href="{{ route('site.home') }}" class="hover:text-on-primary">{{ __('Beranda') }}</a>
            @foreach ($crumbs as $label => $url)
                <x-site.icon name="chevron_right" class="text-[16px]!" />
                <a href="{{ $url }}" class="hover:text-on-primary">{{ $label }}</a>
            @endforeach
        </nav>
        <h1 class="text-3xl font-bold tracking-tight md:text-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-3 max-w-2xl text-on-primary/80">{{ $description }}</p>
        @endif
    </div>
</section>
