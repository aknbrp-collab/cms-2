{{-- Ma'had Aly theme layout (design: docs/req/design-ui/mahad aly). Expects $site, $seo and $slot. --}}
@php
    use App\Enums\MenuLocation;

    $theme = app(App\Support\SiteTheme::class);
    $headerMenu = $theme->menu(MenuLocation::Header);
    $topBarMenu = $theme->menu(MenuLocation::TopBar);
    $footerMenu = $theme->menu(MenuLocation::Footer);
    $prayer = $theme->prayer();
    $logo = $site->brandingUrl('logo');
    $logoDark = $site->brandingUrl('logo_dark');
    $calligraphy = $site->brandingUrl('calligraphy');
    $calligraphyDark = $site->brandingUrl('calligraphy_dark');
    $hasCta = $site->setting('header.cta_url') && $site->setting('header.cta_label');
    $navLink = 'border-b-2 py-1 text-sm font-bold transition-colors duration-150';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('sites.partials.head')
</head>
<body x-data="{ open: false }" x-on:keydown.escape="open = false" class="flex min-h-screen flex-col">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[70] focus:rounded focus:bg-primary focus:px-4 focus:py-2 focus:text-on-primary">{{ __('Langsung ke konten') }}</a>

    {{-- Institutional utility bar --}}
    <div class="border-b border-primary-container bg-primary text-on-primary">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-1 px-4 py-2 sm:px-6 lg:px-10">
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-xs font-bold tracking-wide">
                <x-site.icon name="mosque" class="text-[18px]! text-secondary-container" />
                <span class="tabular-nums">
                    @if ($prayer)
                        {{ $prayer['hijri'] }} /
                    @endif
                    {{ now()->translatedFormat('d F Y') }}
                </span>
                @if ($prayer)
                    <span x-data="prayerCountdown(@js($prayer['times']), {{ $prayer['offset'] }})" x-show="label" x-cloak class="hidden items-center gap-1 text-secondary-container sm:inline-flex">
                        <span aria-hidden="true">•</span> <span x-text="label"></span>
                    </span>
                @endif
            </div>
            @if ($topBarMenu->isNotEmpty())
                <nav class="hidden items-center gap-6 sm:flex" aria-label="{{ __('Tautan cepat') }}">
                    @foreach ($topBarMenu as $item)
                        <a href="{{ $item->href() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="text-xs font-bold text-on-primary transition-colors hover:text-secondary-container">{{ $item->label }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    {{-- Sticky main header --}}
    <header class="sticky top-0 z-50 border-b border-border-subtle bg-surface-card/95 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-10">
            {{-- Brand: logo and calligraphy only; the name stays for screen readers. --}}
            <a href="{{ route('site.home') }}" class="flex min-w-0 items-center gap-3">
                <span class="sr-only">{{ $site->name }}</span>
                @if ($logo)
                    <img src="{{ $logo }}" alt="" @class(['size-12 shrink-0 object-contain', 'dark:hidden' => $logoDark])>
                    @if ($logoDark)
                        <img src="{{ $logoDark }}" alt="" class="hidden size-12 shrink-0 object-contain dark:block">
                    @endif
                @else
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-md bg-primary text-secondary-container"><x-site.icon name="account_balance" class="text-2xl!" /></span>
                @endif
                @if ($calligraphy)
                    <img src="{{ $calligraphy }}" alt="" @class(['h-10 w-auto max-w-[220px] object-contain', 'dark:hidden' => $calligraphyDark])>
                    @if ($calligraphyDark)
                        <img src="{{ $calligraphyDark }}" alt="" class="hidden h-10 w-auto max-w-[220px] object-contain dark:block">
                    @endif
                @elseif ($calligraphyDark)
                    {{-- Only a dark-mode version uploaded: it is made for dark backgrounds. --}}
                    <img src="{{ $calligraphyDark }}" alt="" class="hidden h-10 w-auto max-w-[220px] object-contain dark:block">
                @elseif (! $logo)
                    {{-- Nothing uploaded yet: show the name so the header is not empty. --}}
                    <span class="truncate font-display text-2xl leading-none font-semibold text-brand" aria-hidden="true">{{ $site->name }}</span>
                @endif
            </a>

            <nav class="hidden items-center gap-7 whitespace-nowrap xl:flex" aria-label="{{ __('Navigasi utama') }}">
                <a href="{{ route('site.home') }}" @class([$navLink, 'border-brand text-brand' => request()->routeIs('site.home'), 'border-transparent text-on-surface-variant hover:text-brand' => ! request()->routeIs('site.home')])>{{ __('Beranda') }}</a>
                @foreach ($headerMenu as $item)
                    @if ($item->children->isNotEmpty())
                        <div class="group relative">
                            <button type="button" class="{{ $navLink }} flex items-center gap-1 border-transparent text-on-surface-variant hover:text-brand" aria-haspopup="true">
                                {{ $item->label }} <x-site.icon name="expand_more" class="text-[16px]!" />
                            </button>
                            <div class="invisible absolute start-0 top-full z-50 w-64 pt-3 opacity-0 transition duration-150 group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                                <div class="rounded-md border border-border-subtle bg-surface-card py-2 shadow-lg">
                                    @foreach ($item->children as $child)
                                        <a href="{{ $child->href() }}" @if ($child->open_in_new_tab) target="_blank" rel="noopener" @endif class="block px-4 py-2 text-sm text-on-surface-variant transition-colors hover:bg-surface-low hover:text-brand">{{ $child->label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ $item->href() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="{{ $navLink }} border-transparent text-on-surface-variant hover:text-brand">{{ $item->label }}</a>
                    @endif
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center gap-3">
                <x-site.theme-toggle class="rounded-full! border border-border-subtle" />
                @if ($hasCta)
                    <a href="{{ $site->setting('header.cta_url') }}" class="site-btn-primary hidden sm:inline-flex">{{ $site->setting('header.cta_label') }}</a>
                @endif
                <button type="button" class="rounded-full border border-border-subtle p-2 text-on-surface-variant hover:bg-surface-low xl:hidden" x-on:click="open = true" aria-label="{{ __('Buka menu') }}">
                    <x-site.icon name="menu" />
                </button>
            </div>
        </div>
    </header>

    <x-site.mobile-nav :items="$headerMenu">
        @if ($topBarMenu->isNotEmpty())
            <div class="space-y-1 border-t border-border-subtle p-3">
                @foreach ($topBarMenu as $item)
                    <a href="{{ $item->href() }}" class="block rounded px-3 py-2 text-sm text-on-surface-variant hover:bg-surface-low">{{ $item->label }}</a>
                @endforeach
            </div>
        @endif
        @if ($hasCta)
            <div class="border-t border-border-subtle p-4">
                <a href="{{ $site->setting('header.cta_url') }}" class="site-btn-primary w-full">{{ $site->setting('header.cta_label') }}</a>
            </div>
        @endif
    </x-site.mobile-nav>

    <main id="konten" class="flex-1">
        {{ $slot }}
    </main>

    {{-- Institutional footer --}}
    <footer class="border-t border-border-subtle bg-footer text-on-footer">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-10">
            <div class="flex flex-col gap-3 lg:col-span-2">
                <div class="flex items-center gap-3">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="size-11 object-contain">
                    @endif
                    <div>
                        <p class="font-display text-2xl leading-tight font-semibold text-on-primary">{{ $site->name }}</p>
                        @if ($site->setting('identity.tagline'))
                            <p class="text-xs font-bold tracking-wide text-on-footer/70">{{ $site->setting('identity.tagline') }}</p>
                        @endif
                    </div>
                </div>
                @if ($site->setting('identity.description'))
                    <p class="max-w-md text-sm leading-[22px] text-on-footer/80">{{ $site->setting('identity.description') }}</p>
                @endif
                @if ($site->setting('contact.address'))
                    <p class="flex max-w-md items-start gap-2 text-sm leading-[22px] text-on-footer/80"><x-site.icon name="location_on" class="text-[18px]! text-secondary-container" /> {{ $site->setting('contact.address') }}</p>
                @endif
            </div>

            <div class="flex flex-col gap-3">
                <h2 class="text-sm font-bold text-secondary-container">{{ __('Tautan') }}</h2>
                <ul class="flex flex-col gap-2 text-sm">
                    @forelse ($footerMenu as $item)
                        <li><a href="{{ $item->href() }}" class="transition-colors hover:text-secondary-container">{{ $item->label }}</a></li>
                    @empty
                        <li><a href="{{ route('site.posts.index') }}" class="transition-colors hover:text-secondary-container">{{ __('Berita') }}</a></li>
                        <li><a href="{{ route('site.announcements') }}" class="transition-colors hover:text-secondary-container">{{ __('Pengumuman') }}</a></li>
                    @endforelse
                </ul>
            </div>

            <div class="flex flex-col gap-3">
                <h2 class="text-sm font-bold text-secondary-container">{{ __('Sekretariat') }}</h2>
                <ul class="flex flex-col gap-2 text-sm">
                    @if ($email = $site->setting('contact.email'))
                        <li class="font-mono text-[13px] break-all"><a href="mailto:{{ $email }}" class="transition-colors hover:text-secondary-container">{{ $email }}</a></li>
                    @endif
                    @if ($phone = $site->setting('contact.phone'))
                        <li class="font-mono text-[13px] tabular-nums"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="transition-colors hover:text-secondary-container">{{ $phone }}</a></li>
                    @endif
                    @if ($whatsapp = preg_replace('/[^0-9]/', '', (string) $site->setting('contact.whatsapp')))
                        <li><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 transition-colors hover:text-secondary-container"><x-site.icon name="chat" class="text-[16px]!" /> WhatsApp</a></li>
                    @endif
                    @if ($site->setting('footer.text'))
                        <li class="text-on-footer/70">{{ $site->setting('footer.text') }}</li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="border-t border-on-footer/15 bg-footer-bar">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-4 text-sm text-on-footer/80 sm:flex-row sm:px-6 lg:px-10">
                <span>© {{ now()->year }} {{ $site->name }}. {{ __('Hak cipta dilindungi.') }}</span>
                <span class="font-arabic text-base" lang="ar" dir="rtl">تَفَقُّهٌ فِي الدِّينِ</span>
            </div>
        </div>
    </footer>

    {!! $site->setting('analytics.body_code') !!}
</body>
</html>
