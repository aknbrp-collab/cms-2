{{-- Contact details and map from site settings. Expects $site. --}}
@php
    $mapSrc = App\Support\Embed::toEmbedUrl($site->setting('contact.map_embed_url'));
@endphp

<div class="site-card site-accent-top space-y-4 p-6">
    <h2 class="site-section-title text-lg!">{{ __('Hubungi Kami') }}</h2>
    <ul class="space-y-3 text-sm text-on-surface-variant">
        @if ($address = $site->setting('contact.address'))
            <li class="flex gap-2"><x-site.icon name="location_on" class="text-brand" /> <span>{{ $address }}</span></li>
        @endif
        @if ($phone = $site->setting('contact.phone'))
            <li class="flex gap-2"><x-site.icon name="call" class="text-brand" /> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-brand">{{ $phone }}</a></li>
        @endif
        @if ($email = $site->setting('contact.email'))
            <li class="flex gap-2"><x-site.icon name="mail" class="text-brand" /> <a href="mailto:{{ $email }}" class="hover:text-brand">{{ $email }}</a></li>
        @endif
    </ul>
    @if ($whatsapp = $site->setting('contact.whatsapp'))
        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $whatsapp) }}" target="_blank" rel="noopener" class="site-btn-primary w-full">
            <x-site.icon name="chat" class="text-[18px]!" /> {{ __('Chat WhatsApp') }}
        </a>
    @endif
    @if ($mapSrc)
        <iframe src="{{ $mapSrc }}" class="h-56 w-full rounded-lg border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="{{ __('Peta lokasi') }}"></iframe>
    @endif
</div>
