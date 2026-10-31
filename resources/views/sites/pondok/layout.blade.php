{{-- Pondok Pesantren theme layout. Expects $site, $seo and $slot. --}}
@php
    use App\Enums\MenuLocation;

    $theme = app(App\Support\SiteTheme::class);
    $headerMenu = $theme->menu(MenuLocation::Header);
    $footerMenu = $theme->menu(MenuLocation::Footer);
    $logo = $site->brandingUrl('logo');
    $phone = $site->setting('contact.phone');
    $email = $site->setting('contact.email');
    $whatsapp = preg_replace('/[^0-9]/', '', (string) $site->setting('contact.whatsapp'));
    $socials = array_filter([
        'play_circle' => $site->setting('social.youtube'),
        'photo_camera' => $site->setting('social.instagram'),
        'public' => $site->setting('social.facebook'),
        'music_note' => $site->setting('social.tiktok'),
    ]);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    @include('sites.partials.head')
</head>
<body x-data="{ open: false }" x-on:keydown.escape="open = false">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[70] focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-on-primary">{{ __('Langsung ke konten') }}</a>

    <header class="sticky top-0 z-50 bg-surface-card/95 shadow-[0_1px_8px_rgba(0,0,0,0.04)] backdrop-blur-xl">
        @if ($site->setting('header.announcement') || $phone || $email)
            <div class="hidden bg-primary px-10 py-1.5 text-xs font-semibold tracking-wide text-on-primary lg:block">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-6">
                    <span class="truncate">{{ $site->setting('header.announcement') }}</span>
                    <div class="flex shrink-0 items-center gap-4">
                        @if ($phone)
                            <span class="flex items-center gap-1"><x-site.icon name="call" class="text-[14px]!" /> {{ $phone }}</span>
                        @endif
                        @if ($email)
                            <span class="flex items-center gap-1"><x-site.icon name="mail" class="text-[14px]!" /> {{ $email }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
            <a href="{{ route('site.home') }}" class="flex min-w-0 items-center gap-3">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $site->name }}" class="h-10 w-auto shrink-0 object-contain">
                @else
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-on-primary"><x-site.icon name="mosque" /></span>
                @endif
                <span class="truncate text-lg font-bold tracking-tight text-primary">{{ $site->name }}</span>
            </a>

            <nav class="hidden items-center gap-6 text-sm font-semibold xl:flex" aria-label="{{ __('Navigasi utama') }}">
                <a href="{{ route('site.home') }}" @class(['transition-colors', 'text-primary' => request()->routeIs('site.home'), 'text-on-surface-variant hover:text-primary' => ! request()->routeIs('site.home')])>{{ __('Beranda') }}</a>
                @foreach ($headerMenu as $item)
                    @if ($item->children->isNotEmpty())
                        <div class="group relative">
                            <button type="button" class="flex items-center gap-1 text-on-surface-variant transition-colors hover:text-primary" aria-haspopup="true">
                                {{ $item->label }} <x-site.icon name="expand_more" class="text-[16px]!" />
                            </button>
                            <div class="invisible absolute start-0 top-full z-50 w-56 pt-3 opacity-0 transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                                <div class="rounded-lg border border-border-subtle bg-surface-card py-2 shadow-[0_10px_15px_-3px_rgba(6,78,59,0.08)]">
                                    @foreach ($item->children as $child)
                                        <a href="{{ $child->href() }}" @if ($child->open_in_new_tab) target="_blank" rel="noopener" @endif class="block px-4 py-2 text-sm font-normal text-on-surface-variant transition-colors hover:bg-surface-low hover:text-primary">{{ $child->label }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ $item->href() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="text-on-surface-variant transition-colors hover:text-primary">{{ $item->label }}</a>
                    @endif
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center gap-2">
                @if ($site->setting('header.cta_url') && $site->setting('header.cta_label'))
                    <a href="{{ $site->setting('header.cta_url') }}" class="hidden items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary shadow-sm transition-colors hover:bg-deep sm:inline-flex">{{ $site->setting('header.cta_label') }}</a>
                @endif
                <button type="button" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-low xl:hidden" x-on:click="open = true" aria-label="{{ __('Buka menu') }}">
                    <x-site.icon name="menu" />
                </button>
            </div>
        </div>
    </header>

    <x-site.mobile-nav :items="$headerMenu">
        @if ($site->setting('header.cta_url') && $site->setting('header.cta_label'))
            <div class="border-t border-border-subtle p-4">
                <a href="{{ $site->setting('header.cta_url') }}" class="block rounded-lg bg-primary px-4 py-2.5 text-center font-semibold text-on-primary">{{ $site->setting('header.cta_label') }}</a>
            </div>
        @endif
    </x-site.mobile-nav>

    <main id="konten">
        {{ $slot }}
    </main>

    @if ($whatsapp)
        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="fixed end-5 bottom-5 z-40 inline-flex items-center gap-2 rounded-full bg-[#15803d] px-4 py-3 text-sm font-semibold text-white shadow-2xl transition hover:scale-105" aria-label="{{ __('Chat WhatsApp') }}">
            <x-site.icon name="chat" class="text-[20px]!" /> <span class="hidden sm:inline">{{ __('Tanya Admin') }}</span>
        </a>
    @endif

    <footer class="mt-16 border-t border-border-subtle bg-footer text-on-footer">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-10">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="h-9 w-auto object-contain">
                    @endif
                    <span class="text-lg font-bold text-primary">{{ $site->name }}</span>
                </div>
                @if ($site->setting('identity.description'))
                    <p class="text-sm leading-relaxed text-on-surface-variant">{{ $site->setting('identity.description') }}</p>
                @endif
                @if ($site->setting('contact.address'))
                    <p class="flex items-start gap-2 text-sm text-on-surface-variant"><x-site.icon name="location_on" class="text-[18px]! text-primary" /> {{ $site->setting('contact.address') }}</p>
                @endif
            </div>

            <div class="space-y-4">
                <h2 class="inline-block border-b-2 border-primary pb-1 font-semibold">{{ __('Informasi') }}</h2>
                <ul class="space-y-2.5 text-sm text-on-surface-variant">
                    @forelse ($footerMenu as $item)
                        <li><a href="{{ $item->href() }}" class="flex items-center gap-1 hover:text-primary"><x-site.icon name="chevron_right" class="text-[16px]! text-primary" /> {{ $item->label }}</a></li>
                    @empty
                        <li><a href="{{ route('site.posts.index') }}" class="flex items-center gap-1 hover:text-primary"><x-site.icon name="chevron_right" class="text-[16px]! text-primary" /> {{ __('Berita') }}</a></li>
                        <li><a href="{{ route('site.announcements') }}" class="flex items-center gap-1 hover:text-primary"><x-site.icon name="chevron_right" class="text-[16px]! text-primary" /> {{ __('Pengumuman') }}</a></li>
                    @endforelse
                </ul>
            </div>

            <div class="space-y-4">
                <h2 class="inline-block border-b-2 border-primary pb-1 font-semibold">{{ __('Hubungi Kami') }}</h2>
                <ul class="space-y-3 text-sm text-on-surface-variant">
                    @if ($phone)
                        <li class="flex items-center gap-2"><x-site.icon name="call" class="text-[18px]! text-primary" /> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-primary">{{ $phone }}</a></li>
                    @endif
                    @if ($email)
                        <li class="flex items-center gap-2"><x-site.icon name="mail" class="text-[18px]! text-primary" /> <a href="mailto:{{ $email }}" class="hover:text-primary">{{ $email }}</a></li>
                    @endif
                    @if ($whatsapp)
                        <li class="flex items-center gap-2"><x-site.icon name="chat" class="text-[18px]! text-primary" /> <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="hover:text-primary">WhatsApp</a></li>
                    @endif
                </ul>
            </div>

            <div class="space-y-4">
                <h2 class="inline-block border-b-2 border-primary pb-1 font-semibold">{{ __('Media Sosial') }}</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($socials as $icon => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="flex size-10 items-center justify-center rounded-lg border border-border-subtle bg-surface-card text-on-surface-variant transition hover:border-primary hover:text-primary" aria-label="{{ parse_url($url, PHP_URL_HOST) }}">
                            <x-site.icon :name="$icon" />
                        </a>
                    @endforeach
                </div>
                @if ($site->setting('footer.text'))
                    <p class="text-sm text-on-surface-variant">{{ $site->setting('footer.text') }}</p>
                @endif
            </div>
        </div>

        <div class="bg-footer-bar py-4 text-xs text-on-primary/80">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 sm:flex-row sm:px-6 lg:px-10">
                <span>© {{ now()->year }} {{ $site->name }}. {{ __('Hak cipta dilindungi.') }}</span>
                <span class="flex gap-4">
                    <a href="{{ route('site.posts.index') }}" class="hover:underline">{{ __('Berita') }}</a>
                    <a href="{{ route('site.announcements') }}" class="hover:underline">{{ __('Pengumuman') }}</a>
                </span>
            </div>
        </div>
    </footer>

    {!! $site->setting('analytics.body_code') !!}
</body>
</html>
