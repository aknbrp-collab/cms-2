<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $site->name }}</title>
    </head>
    <body style="font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0;">
        {{-- Placeholder until the public themes are built in Phase 2. --}}
        <main style="text-align: center;">
            <h1>{{ $site->name }}</h1>
            <p>{{ __('Website is under construction.') }}</p>
        </main>
    </body>
</html>
