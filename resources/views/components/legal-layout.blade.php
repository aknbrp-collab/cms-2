@props(['title'])

{{-- Minimal public layout for legal pages on the admin domain. --}}
@php($pondok = App\Models\Site::findByKey(App\Enums\SiteKey::Pondok))
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; line-height: 1.65; color: #1f2937; background: #f8fafc; margin: 0; }
        main { max-width: 46rem; margin: 0 auto; padding: 3rem 1.25rem; }
        h1 { color: #064e3b; font-size: 1.9rem; line-height: 1.2; }
        h2 { color: #064e3b; font-size: 1.2rem; margin-top: 2rem; }
        a { color: #047857; }
        .meta { color: #64748b; font-size: .9rem; }
        .box { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1rem 1.25rem; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p class="meta">{{ $pondok?->name }} &middot; {{ __('Terakhir diperbarui') }}: 31 Oktober 2026</p>
        {{ $slot }}
        <p class="meta" style="margin-top:3rem">{{ __('Kontak') }}: {{ $pondok?->setting('contact.email') ?: 'info@'.parse_url(config('app.url'), PHP_URL_HOST) }}</p>
    </main>
</body>
</html>
