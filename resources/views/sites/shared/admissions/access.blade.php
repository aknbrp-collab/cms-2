@php
    $input = 'mt-1 block w-full rounded-lg border border-border-subtle bg-surface-card px-3 py-2.5 text-on-surface shadow-sm focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none';
    $label = 'block text-sm font-semibold text-on-surface';
    $error = 'mt-1 text-sm text-red-600 dark:text-red-400';
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Lengkapi Berkas')" :description="__('Unggah atau perbaiki berkas pendaftaran yang sudah dikirim.')" :crumbs="[__('Penerimaan') => route('site.admissions.show')]" />

    <div class="mx-auto max-w-lg px-4 py-10 sm:px-6 md:py-14">
        <form method="POST" action="{{ route('site.admissions.access.store') }}" class="site-card space-y-5 p-6 md:p-8" novalidate>
            @csrf
            <p class="text-sm text-on-surface-variant">{{ __('Masukkan nomor pendaftaran dan tanggal lahir calon peserta sesuai formulir.') }}</p>

            <div>
                <label for="registration_number" class="{{ $label }}">{{ __('Nomor pendaftaran') }}</label>
                <input id="registration_number" name="registration_number" value="{{ old('registration_number') }}" required maxlength="30" placeholder="REG-2611-XXXXXX" autocomplete="off" class="{{ $input }} font-mono uppercase">
                @error('registration_number') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="birth_date" class="{{ $label }}">{{ __('Tanggal lahir') }}</label>
                <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date') }}" required class="{{ $input }}">
                @error('birth_date') <p class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="site-btn-primary w-full py-3">
                {{ __('Buka halaman berkas') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
            </button>
        </form>
    </div>
</x-site.layout>
