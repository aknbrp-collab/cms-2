@php
    use App\Enums\LegalCategory;

    $input = 'mt-1 block w-full rounded-lg border border-border-subtle bg-surface-card px-3 py-2.5 text-on-surface shadow-sm focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none';
    $label = 'block text-sm font-semibold text-on-surface';
    $error = 'mt-1 text-sm text-red-600 dark:text-red-400';
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Ajukan Pertanyaan')" :description="__('Pertanyaan akan ditinjau lalu dikaji oleh dewan asatidz. Jawaban terpilih diterbitkan di arsip Tanya Hukum.')" :crumbs="[__('Tanya Hukum') => route('site.legal-questions.index')]" />

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 md:py-14">
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-sm text-red-700 dark:text-red-300" role="alert">{{ __('Mohon periksa kembali isian yang ditandai.') }}</div>
        @endif

        <form method="POST" action="{{ route('site.legal-questions.store') }}" class="site-card space-y-6 p-6 md:p-8" x-data="{ anonymous: @js((bool) old('is_anonymous')) }" novalidate>
            @csrf

            {{-- Honeypot: humans never see or fill this field. --}}
            <div class="hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <label for="category" class="{{ $label }}">{{ __('Kategori') }} *</label>
                <select id="category" name="category" required class="{{ $input }}">
                    <option value="">{{ __('Pilih kategori') }}</option>
                    @foreach (LegalCategory::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('category') === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
                @error('category') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="question" class="{{ $label }}">{{ __('Pertanyaan') }} *</label>
                <textarea id="question" name="question" rows="7" required minlength="30" maxlength="5000" class="{{ $input }}">{{ old('question') }}</textarea>
                <p class="mt-1 text-xs text-muted">{{ __('Tuliskan pertanyaan dengan jelas, minimal 30 karakter.') }}</p>
                @error('question') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-3 text-sm font-medium text-on-surface">
                <input type="checkbox" name="is_anonymous" value="1" x-model="anonymous" class="size-4 accent-brand">
                {{ __('Kirim sebagai anonim (nama tidak ditampilkan)') }}
            </label>

            <div class="grid gap-5 sm:grid-cols-2" x-show="! anonymous">
                <div>
                    <label for="asker_name" class="{{ $label }}">{{ __('Nama') }} *</label>
                    <input id="asker_name" name="asker_name" value="{{ old('asker_name') }}" maxlength="100" autocomplete="name" class="{{ $input }}">
                    @error('asker_name') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="asker_city" class="{{ $label }}">{{ __('Kota') }}</label>
                    <input id="asker_city" name="asker_city" value="{{ old('asker_city') }}" maxlength="100" class="{{ $input }}">
                </div>
            </div>

            <div>
                <label for="asker_email" class="{{ $label }}">{{ __('Email (opsional, tidak ditampilkan)') }}</label>
                <input id="asker_email" type="email" name="asker_email" value="{{ old('asker_email') }}" maxlength="255" autocomplete="email" class="{{ $input }}">
                @error('asker_email') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-3 text-sm text-on-surface-variant">
                <input type="checkbox" name="agree" value="1" @checked(old('agree')) class="mt-0.5 size-4 accent-brand">
                <span>{{ __('Saya setuju pertanyaan ini (tanpa data pribadi) dapat diterbitkan beserta jawabannya.') }}</span>
            </label>
            @error('agree') <p class="{{ $error }}">{{ $message }}</p> @enderror

            <button type="submit" class="site-btn-primary w-full py-3 sm:w-auto">
                {{ __('Kirim Pertanyaan') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
            </button>
        </form>
    </div>
</x-site.layout>
