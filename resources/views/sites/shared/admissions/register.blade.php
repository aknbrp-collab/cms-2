@php
    $input = 'mt-1 block w-full rounded-lg border border-border-subtle bg-surface-card px-3 py-2.5 text-on-surface shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none';
    $label = 'block text-sm font-semibold text-on-surface';
    $error = 'mt-1 text-sm text-red-600';
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Formulir Pendaftaran')" :description="$period->title.' • '.$period->academic_year" :crumbs="[__('Penerimaan') => route('site.admissions.show')]" />

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 md:py-14">
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
                {{ __('Mohon periksa kembali isian yang ditandai.') }}
            </div>
        @endif

        <form method="POST" action="{{ route('site.admissions.store') }}" class="space-y-8" novalidate>
            @csrf

            {{-- Honeypot: humans never see or fill this field. --}}
            <div class="hidden" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <fieldset class="space-y-5 rounded-2xl bg-surface-card p-6 shadow-sm md:p-8">
                <legend class="sr-only">{{ __('Data Calon Santri') }}</legend>
                <h2 class="text-lg font-bold text-primary" aria-hidden="true">{{ __('Data Calon Santri') }}</h2>

                <div>
                    <label for="full_name" class="{{ $label }}">{{ __('Nama lengkap') }} *</label>
                    <input id="full_name" name="full_name" value="{{ old('full_name') }}" required maxlength="255" autocomplete="name" class="{{ $input }}">
                    @error('full_name') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <span class="{{ $label }}">{{ __('Jenis kelamin') }} *</span>
                        <div class="mt-2 flex gap-4 text-sm">
                            <label class="inline-flex items-center gap-2"><input type="radio" name="gender" value="L" @checked(old('gender') === 'L') class="accent-primary"> {{ __('Laki-laki') }}</label>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="gender" value="P" @checked(old('gender') === 'P') class="accent-primary"> {{ __('Perempuan') }}</label>
                        </div>
                        @error('gender') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="birth_place" class="{{ $label }}">{{ __('Tempat lahir') }} *</label>
                        <input id="birth_place" name="birth_place" value="{{ old('birth_place') }}" required maxlength="100" class="{{ $input }}">
                        @error('birth_place') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="birth_date" class="{{ $label }}">{{ __('Tanggal lahir') }} *</label>
                        <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date') }}" required class="{{ $input }}">
                        @error('birth_date') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="previous_school" class="{{ $label }}">{{ __('Asal sekolah') }} *</label>
                        <input id="previous_school" name="previous_school" value="{{ old('previous_school') }}" required maxlength="255" class="{{ $input }}">
                        @error('previous_school') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="nisn" class="{{ $label }}">{{ __('NISN') }}</label>
                        <input id="nisn" name="nisn" value="{{ old('nisn') }}" inputmode="numeric" maxlength="10" class="{{ $input }}">
                        @error('nisn') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($period->programs)
                    <div>
                        <label for="program" class="{{ $label }}">{{ __('Program pilihan') }} *</label>
                        <select id="program" name="program" required class="{{ $input }}">
                            <option value="">{{ __('Pilih program') }}</option>
                            @foreach ($period->programs as $program)
                                <option value="{{ $program }}" @selected(old('program') === $program)>{{ $program }}</option>
                            @endforeach
                        </select>
                        @error('program') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                @endif
            </fieldset>

            <fieldset class="space-y-5 rounded-2xl bg-surface-card p-6 shadow-sm md:p-8">
                <legend class="sr-only">{{ __('Data Orang Tua / Wali') }}</legend>
                <h2 class="text-lg font-bold text-primary" aria-hidden="true">{{ __('Data Orang Tua / Wali') }}</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="parent_name" class="{{ $label }}">{{ __('Nama orang tua/wali') }} *</label>
                        <input id="parent_name" name="parent_name" value="{{ old('parent_name') }}" required maxlength="255" class="{{ $input }}">
                        @error('parent_name') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="parent_phone" class="{{ $label }}">{{ __('Nomor HP / WhatsApp') }} *</label>
                        <input id="parent_phone" type="tel" name="parent_phone" value="{{ old('parent_phone') }}" required placeholder="081234567890" autocomplete="tel" class="{{ $input }}">
                        @error('parent_phone') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="email" class="{{ $label }}">{{ __('Email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" class="{{ $input }}">
                    @error('email') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="address" class="{{ $label }}">{{ __('Alamat lengkap') }} *</label>
                    <textarea id="address" name="address" rows="3" required maxlength="1000" autocomplete="street-address" class="{{ $input }}">{{ old('address') }}</textarea>
                    @error('address') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <div class="space-y-4">
                <label class="flex items-start gap-3 text-sm text-on-surface-variant">
                    <input type="checkbox" name="agree" value="1" @checked(old('agree')) class="mt-0.5 size-4 accent-primary">
                    <span>{{ __('Saya menyatakan data yang diisi benar dan bersedia dihubungi oleh panitia penerimaan. Data hanya digunakan untuk keperluan pendaftaran.') }}</span>
                </label>
                @error('agree') <p class="{{ $error }}">{{ $message }}</p> @enderror

                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 font-semibold text-on-primary shadow-sm transition hover:bg-primary-container sm:w-auto">
                    {{ __('Kirim Pendaftaran') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
                </button>
            </div>
        </form>
    </div>
</x-site.layout>
