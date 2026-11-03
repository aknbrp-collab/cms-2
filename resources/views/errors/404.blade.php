{{-- On a public site the 404 page uses the site theme; elsewhere the framework's plain page. --}}
@php
    $site = current_site();
    $onPublicSite = $site !== null && request()->getHost() === $site->domain;
@endphp

@if ($onPublicSite)
    <x-site.layout :site="$site" :seo="App\Support\Seo::forSite($site, __('Halaman tidak ditemukan'))">
        <div class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center">
            <p class="text-6xl font-extrabold text-brand">404</p>
            <h1 class="mt-4 text-2xl font-bold text-on-surface">{{ __('Halaman tidak ditemukan') }}</h1>
            <p class="mt-2 text-on-surface-variant">{{ __('Halaman yang Anda cari mungkin telah dipindahkan atau tidak tersedia.') }}</p>
            <a href="{{ route('site.home') }}" class="mt-8 inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-semibold text-on-primary hover:bg-primary-container">
                <x-site.icon name="arrow_back" class="text-[18px]!" /> {{ __('Kembali ke beranda') }}
            </a>
        </div>
    </x-site.layout>
@else
    @section('title', __('Not Found'))
    @section('code', '404')
    @section('message', __('Not Found'))
    @include('errors::minimal')
@endif
