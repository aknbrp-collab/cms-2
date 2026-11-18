@php
    use App\Enums\DocumentStatus;
    use App\Models\AdmissionDocument;

    $badge = [
        DocumentStatus::Pending->value => 'bg-surface-container text-on-surface-variant',
        DocumentStatus::Accepted->value => 'bg-mint text-brand',
        DocumentStatus::NeedsFix->value => 'bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200',
    ];
@endphp

<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="__('Unggah Berkas')" :description="$registration->period->title.' • '.$registration->period->academic_year" :crumbs="[__('Penerimaan') => route('site.admissions.show')]" />

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 md:py-14">
        @include('sites.shared.admissions.steps', ['current' => 2])

        <div class="site-card mb-6 p-5">
            <p class="text-sm text-muted">{{ __('Nomor pendaftaran') }}</p>
            <p class="font-mono text-xl font-bold tracking-wider text-brand">{{ $registration->registration_number }}</p>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $registration->full_name }}</p>
            <p class="mt-3 text-sm text-on-surface-variant">
                {{ __(':uploaded dari :total berkas sudah diunggah.', ['uploaded' => $summary['uploaded'], 'total' => $summary['total']]) }}
                @if ($summary['missing_required'] > 0)
                    {{ __(':count berkas wajib belum diunggah.', ['count' => $summary['missing_required']]) }}
                @endif
            </p>
        </div>

        @if (session('uploaded'))
            <div class="mb-6 rounded-xl border border-brand/30 bg-mint p-4 text-sm font-medium text-brand" role="status">{{ session('uploaded') }}</div>
        @endif

        <div class="space-y-4">
            @foreach ($requirements as $requirement)
                @php
                    $document = $documents->get($requirement->id);
                    $canUpload = AdmissionDocument::applicantCanUpload($registration, $document);
                    $bag = $errors->getBag('upload_'.$requirement->id);
                @endphp
                <section id="berkas-{{ $requirement->id }}" class="site-card scroll-mt-28 p-5 md:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-bold text-on-surface">
                                {{ $requirement->name }}
                                @if ($requirement->is_required)
                                    <span class="text-red-600 dark:text-red-400" aria-label="{{ __('wajib') }}">*</span>
                                @endif
                            </h2>
                            @if ($requirement->description)
                                <p class="mt-1 text-sm text-on-surface-variant">{{ $requirement->description }}</p>
                            @endif
                            <p class="mt-1 text-xs text-muted">{{ $requirement->limitsLabel() }}</p>
                        </div>
                        @if ($document)
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge[$document->status->value] }}">{{ $document->status->publicLabel() }}</span>
                        @else
                            <span class="rounded-full bg-surface-container px-3 py-1 text-xs font-bold text-muted">{{ __('Belum diunggah') }}</span>
                        @endif
                    </div>

                    @if ($document?->status === DocumentStatus::NeedsFix && $document->note)
                        <div class="mt-4 rounded-lg border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 p-3 text-sm text-amber-900 dark:text-amber-200">
                            <span class="font-semibold">{{ __('Catatan panitia:') }}</span> {{ $document->note }}
                        </div>
                    @endif

                    @if ($document?->uploaded_at)
                        <p class="mt-3 text-xs text-muted">{{ __('Diunggah :date', ['date' => $document->uploaded_at->translatedFormat('d F Y H:i')]) }}</p>
                    @endif

                    @if ($canUpload)
                        <form
                            method="POST"
                            action="{{ route('site.admissions.documents.upload', $requirement->id) }}"
                            enctype="multipart/form-data"
                            class="mt-4"
                            x-data="{
                                progress: null,
                                error: null,
                                submit(form) {
                                    const xhr = new XMLHttpRequest();
                                    xhr.open('POST', form.action);
                                    xhr.setRequestHeader('Accept', 'application/json');
                                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                                    xhr.upload.onprogress = (event) => { if (event.lengthComputable) this.progress = Math.round(event.loaded / event.total * 100) };
                                    xhr.onload = () => {
                                        if (xhr.status >= 200 && xhr.status < 300) { window.location.hash = 'berkas-{{ $requirement->id }}'; window.location.reload(); return; }
                                        this.progress = null;
                                        if (xhr.status === 429) { this.error = @js(__('Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.')); return; }
                                        try { const data = JSON.parse(xhr.responseText); this.error = data.errors?.file?.[0] ?? data.message; } catch (e) { this.error = @js(__('Unggah gagal. Coba lagi.')); }
                                    };
                                    xhr.onerror = () => { this.progress = null; this.error = @js(__('Koneksi terputus. Coba lagi.')); };
                                    this.error = null;
                                    this.progress = 0;
                                    xhr.send(new FormData(form));
                                },
                            }"
                            x-on:submit.prevent="submit($el)"
                        >
                            @csrf
                            <label for="file-{{ $requirement->id }}" class="sr-only">{{ __('Pilih berkas :name', ['name' => $requirement->name]) }}</label>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <input
                                    id="file-{{ $requirement->id }}"
                                    type="file"
                                    name="file"
                                    required
                                    accept="{{ collect($requirement->extensions())->map(fn ($extension) => '.'.$extension)->join(',') }}"
                                    class="block w-full text-sm text-on-surface-variant file:me-3 file:rounded-lg file:border-0 file:bg-surface-container file:px-4 file:py-2 file:font-semibold file:text-brand hover:file:bg-surface-low"
                                >
                                <button type="submit" x-bind:disabled="progress !== null" class="site-btn-primary shrink-0 disabled:opacity-60">
                                    <x-site.icon name="upload" class="text-[18px]!" /> {{ $document ? __('Ganti berkas') : __('Unggah') }}
                                </button>
                            </div>
                            <div x-show="progress !== null" x-cloak class="mt-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress">
                                <div class="h-2 overflow-hidden rounded-full bg-surface-container"><div class="h-full bg-primary transition-all" x-bind:style="`width: ${progress}%`"></div></div>
                                <p class="mt-1 text-xs text-muted" x-text="progress < 100 ? `${progress}%` : @js(__('Memproses…'))"></p>
                            </div>
                            <p x-show="error" x-text="error" x-cloak class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert"></p>
                            @if ($bag->has('file'))
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $bag->first('file') }}</p>
                            @endif
                        </form>
                    @elseif (! $document)
                        <p class="mt-4 text-sm text-on-surface-variant">{{ __('Unggah berkas sudah ditutup untuk pendaftaran ini.') }}</p>
                    @endif
                </section>
            @endforeach
        </div>

        <div class="mt-8 flex flex-col gap-3 rounded-2xl border-2 border-dashed border-brand/30 p-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-on-surface-variant">
                @if ($summary['missing_required'] > 0)
                    {{ __('Belum lengkap? Anda bisa kembali nanti melalui menu "Lengkapi berkas" dengan nomor pendaftaran dan tanggal lahir.') }}
                @else
                    {{ __('Semua berkas wajib sudah diunggah. Panitia akan memeriksanya.') }}
                @endif
            </p>
            <a href="{{ $finishUrl }}" class="site-btn-primary shrink-0">
                {{ __('Selesai') }} <x-site.icon name="arrow_forward" class="text-[18px]!" />
            </a>
        </div>
    </div>
</x-site.layout>
