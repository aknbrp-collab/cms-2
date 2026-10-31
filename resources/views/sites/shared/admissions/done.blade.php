<x-site.layout :site="$site" :seo="$seo">
    <div class="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6 md:py-24">
        <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-mint text-primary"><x-site.icon name="verified" filled class="text-4xl!" /></span>
        <h1 class="mt-6 text-3xl font-bold text-on-surface">{{ __('Pendaftaran berhasil dikirim') }}</h1>
        <p class="mt-3 text-on-surface-variant">{{ __('Terima kasih, :name. Simpan nomor pendaftaran berikut untuk keperluan konfirmasi.', ['name' => $registration->full_name]) }}</p>

        <div class="mx-auto mt-8 max-w-sm rounded-2xl border-2 border-dashed border-primary/40 bg-surface-card p-6">
            <p class="text-sm text-muted">{{ __('Nomor pendaftaran') }}</p>
            <p class="mt-1 font-mono text-2xl font-bold tracking-wider text-primary">{{ $registration->registration_number }}</p>
            <p class="mt-2 text-xs text-muted">{{ $registration->period->title }} • {{ $registration->period->academic_year }}</p>
        </div>

        <p class="mt-8 text-sm text-on-surface-variant">{{ __('Panitia akan menghubungi Anda melalui nomor HP yang didaftarkan untuk langkah selanjutnya.') }}</p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('site.admissions.show') }}" class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-on-primary hover:bg-primary-container">{{ __('Informasi pendaftaran') }}</a>
            @if ($whatsapp = preg_replace('/[^0-9]/', '', (string) $site->setting('contact.whatsapp')))
                <a href="https://wa.me/{{ $whatsapp }}?text={{ urlencode(__('Assalamualaikum, saya sudah mendaftar dengan nomor :number', ['number' => $registration->registration_number])) }}" target="_blank" rel="noopener" class="rounded-lg border border-primary px-5 py-2.5 font-semibold text-primary hover:bg-surface-low">{{ __('Konfirmasi via WhatsApp') }}</a>
            @endif
        </div>
    </div>
</x-site.layout>
