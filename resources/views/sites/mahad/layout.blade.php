{{-- Ma'had Aly theme layout. Expects $site, $seo and $slot. --}}
@php
    use App\Enums\MenuLocation;

    $theme = app(App\Support\SiteTheme::class);
    $headerMenu = $theme->menu(MenuLocation::Header);
    $topBarMenu = $theme->menu(MenuLocation::TopBar);
    $footerMenu = $theme->menu(MenuLocation::Footer);
    $prayer = $theme->prayer();
    $logo = $site->brandingUrl('logo');
    $calligraphy = $site->brandingUrl('calligraphy');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('sites.partials.head')
</head>
<body x-data="{ open: false }" x-on:keydown.escape="open = false">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[70] focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-on-primary">{{ __('Langsung ke konten') }}</a>

    <header class="sticky top-0 z-50 shadow-[0_1px_8px_rgba(0,0,0,0.04)]">
        <div class="border-b border-primary-container/40 bg-primary text-xs text-on-primary">
            <div class="mx-auto flex h-10 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="hidden font-arabic text-sm text-mint/90 md:inline" lang="ar" dir="rtl">السَّلَامُ عَلَيْكُمْ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ</span>
                    <span class="hidden text-on-primary-container md:inline">•</span>
                    <span class="flex items-center gap-1 truncate">
                        <x-site.icon name="calendar_today" class="text-[14px]! text-gold-light" />
                        @if ($prayer)
                            {{ $prayer['hijri'] }} /
                        @endif
                        {{ now()->translatedFormat('d F Y') }}
                    </span>
                </div>
                <div class="flex shrink-0 items-center gap-4">
                    @if ($prayer)
                        <div x-data="prayerCountdown(@js($prayer['times']), {{ $prayer['offset'] }})" x-show="label" x-cloak class="hidden items-center gap-1.5 rounded-lg bg-primary-container/60 px-2.5 py-0.5 font-semibold text-gold-light lg:flex">
                            <x-site.icon name="schedule" class="text-[14px]!" /> <span x-text="label"></span>
                        </div>
                    @endif
                    @if ($topBarMenu->isNotEmpty())
                        <nav class="hidden items-center gap-3 text-on-primary/80 sm:flex" aria-label="{{ __('Tautan cepat') }}">
                            @foreach ($topBarMenu as $item)
                                @if (! $loop->first)
                                    <span class="text-on-primary-container">•</span>
                                @endif
                                <a href="{{ $item->href() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="transition-colors hover:text-gold-light">{{ $item->label }}</a>
                            @endforeach
                        </nav>
                    @endif
                </div>
            </div>
        </div>

        <div class="border-b border-primary/10 bg-surface/95 backdrop-blur-md">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                <a href="{{ route('site.home') }}" class="flex min-w-0 items-center gap-3.5">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $site->name }}" class="h-12 w-auto shrink-0 object-contain">
                    @else
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-on-primary"><x-site.icon name="menu_book" /></span>
                    @endif
                    <span class="flex min-w-0 flex-col">
                        <span class="flex items-center gap-2">
                            <span class="truncate text-lg leading-none font-bold tracking-tight text-primary uppercase">{{ $site->name }}</span>
                            @if ($calligraphy)
                                <img src="{{ $calligraphy }}" alt="" class="hidden h-7 w-auto object-contain sm:block">
                            @endif
                        </span>
                        @if ($site->setting('identity.tagline'))
                            <span class="mt-1 hidden truncate text-xs font-medium text-on-surface-variant sm:block">{{ $site->setting('identity.tagline') }}</span>
                        @endif
                    </span>
                </a>

                <nav class="hidden items-center gap-0.5 text-sm font-semibold whitespace-nowrap xl:flex" aria-label="{{ __('Navigasi utama') }}">
                    <a href="{{ route('site.home') }}" @class(['rounded-lg px-3 py-2 transition-colors', 'bg-primary-container text-on-primary' => request()->routeIs('site.home'), 'text-on-surface-variant hover:text-on-surface' => ! request()->routeIs('site.home')])>{{ __('Beranda') }}</a>
                    @foreach ($headerMenu as $item)
                        @if ($item->children->isNotEmpty())
                            <div class="group relative">
                                <button type="button" class="flex items-center gap-1 rounded-lg px-2.5 py-2 text-on-surface-variant transition-colors hover:text-on-surface" aria-haspopup="true">
                                    {{ $item->label }} <x-site.icon name="expand_more" class="text-[16px]!" />
                                </button>
                                <div class="invisible absolute start-0 top-full z-50 w-60 pt-2 opacity-0 transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                                    <div class="rounded-xl border border-primary/10 bg-surface-card py-2 shadow-lg">
                                        @foreach ($item->children as $child)
                                            <a href="{{ $child->href() }}" @if ($child->open_in_new_tab) target="_blank" rel="noopener" @endif class="block px-4 py-2 text-sm font-normal text-on-surface-variant hover:bg-surface-low hover:text-on-surface">{{ $child->label }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <a href="{{ $item->href() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="rounded-lg px-2.5 py-2 text-on-surface-variant transition-colors hover:text-on-surface">{{ $item->label }}</a>
                        @endif
                    @endforeach
                </nav>

                <div class="flex shrink-0 items-center gap-2">
                    @if ($site->setting('header.cta_url') && $site->setting('header.cta_label'))
                        <a href="{{ $site->setting('header.cta_url') }}" class="hidden items-center gap-2 rounded bg-secondary px-4 py-2.5 text-sm font-semibold text-on-secondary shadow-sm transition hover:brightness-110 sm:inline-flex">
                            <span class="relative flex size-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-gold-light opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-white"></span></span>
                            {{ $site->setting('header.cta_label') }}
                        </a>
                    @endif
                    <button type="button" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-low xl:hidden" x-on:click="open = true" aria-label="{{ __('Buka menu') }}">
                        <x-site.icon name="menu" />
                    </button>
                </div>
            </div>
        </div>
    </header>

    <x-site.mobile-nav :items="$headerMenu">
        @if ($topBarMenu->isNotEmpty())
            <div class="space-y-1 border-t border-border-subtle p-3">
                @foreach ($topBarMenu as $item)
                    <a href="{{ $item->href() }}" class="block rounded-lg px-3 py-2 text-sm text-on-surface-variant hover:bg-surface-low">{{ $item->label }}</a>
                @endforeach
            </div>
        @endif
        @if ($site->setting('header.cta_url') && $site->setting('header.cta_label'))
            <div class="border-t border-border-subtle p-4">
                <a href="{{ $site->setting('header.cta_url') }}" class="block rounded-lg bg-secondary px-4 py-2.5 text-center font-semibold text-on-secondary">{{ $site->setting('header.cta_label') }}</a>
            </div>
        @endif
    </x-site.mobile-nav>

    <main id="konten">
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t-2 border-secondary/50 bg-footer pt-14 text-on-footer">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-10">
            <div class="space-y-4 lg:col-span-2">
                <div class="flex items-center gap-3">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="h-12 w-auto object-contain">
                    @endif
                    <div>
                        <p class="text-lg font-bold tracking-wide uppercase">{{ $site->name }}</p>
                        @if ($site->setting('identity.tagline'))
                            <p class="text-xs text-on-footer/60">{{ $site->setting('identity.tagline') }}</p>
                        @endif
                    </div>
                </div>
                @if ($site->setting('identity.description'))
                    <p class="max-w-md text-sm leading-relaxed text-on-footer/70">{{ $site->setting('identity.description') }}</p>
                @endif
                @if ($site->setting('contact.address'))
                    <p class="flex items-start gap-2 text-sm text-on-footer/70"><x-site.icon name="location_on" class="text-[18px]! text-gold-light" /> {{ $site->setting('contact.address') }}</p>
                @endif
            </div>

            <div class="space-y-4">
                <h2 class="text-sm font-bold tracking-wider text-gold-light uppercase">{{ __('Tautan') }}</h2>
                <ul class="space-y-2 text-sm text-on-footer/70">
                    @forelse ($footerMenu as $item)
                        <li><a href="{{ $item->href() }}" class="hover:text-gold-light">{{ $item->label }}</a></li>
                    @empty
                        <li><a href="{{ route('site.posts.index') }}" class="hover:text-gold-light">{{ __('Berita') }}</a></li>
                        <li><a href="{{ route('site.announcements') }}" class="hover:text-gold-light">{{ __('Pengumuman') }}</a></li>
                    @endforelse
                </ul>
            </div>

            <div class="space-y-4">
                <h2 class="text-sm font-bold tracking-wider text-gold-light uppercase">{{ __('Sekretariat') }}</h2>
                <ul class="space-y-2 text-sm text-on-footer/70">
                    @if ($phone = $site->setting('contact.phone'))
                        <li class="flex items-center gap-2"><x-site.icon name="call" class="text-[16px]!" /> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-gold-light">{{ $phone }}</a></li>
                    @endif
                    @if ($email = $site->setting('contact.email'))
                        <li class="flex items-center gap-2"><x-site.icon name="mail" class="text-[16px]!" /> <a href="mailto:{{ $email }}" class="hover:text-gold-light">{{ $email }}</a></li>
                    @endif
                    @if ($whatsapp = preg_replace('/[^0-9]/', '', (string) $site->setting('contact.whatsapp')))
                        <li class="flex items-center gap-2"><x-site.icon name="chat" class="text-[16px]!" /> <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="hover:text-gold-light">WhatsApp</a></li>
                    @endif
                </ul>
                @if ($site->setting('footer.text'))
                    <p class="text-xs text-on-footer/60">{{ $site->setting('footer.text') }}</p>
                @endif
            </div>
        </div>

        <div class="mt-12 border-t border-white/10 bg-footer-bar py-5 text-xs text-on-footer/60">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 sm:flex-row sm:px-6 lg:px-10">
                <span>© {{ now()->year }} {{ $site->name }}. {{ __('Hak cipta dilindungi.') }}</span>
                <span class="font-arabic text-sm" lang="ar" dir="rtl">تَفَقُّهٌ فِي الدِّينِ</span>
            </div>
        </div>
    </footer>

    {!! $site->setting('analytics.body_code') !!}
</body>
</html>
