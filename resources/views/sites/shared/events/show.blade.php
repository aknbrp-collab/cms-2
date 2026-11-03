<x-site.layout :site="$site" :seo="$seo">
    <x-site.page-header :title="$event->title" :description="$event->summary" :crumbs="[__('Agenda') => route('site.events.index')]" />

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-10 sm:px-6 md:py-14 lg:grid-cols-12 lg:px-10">
        <div class="space-y-8 lg:col-span-8">
            @if ($event->featuredImage)
                <x-site.image :asset="$event->featuredImage" :alt="$event->title" class="aspect-[16/9] w-full rounded-2xl shadow-md" />
            @endif

            @if ($event->body)
                {{-- Sanitised by App\Support\RichText when saved. --}}
                <div class="prose-site">{!! $event->body !!}</div>
            @endif
        </div>

        <aside class="lg:col-span-4">
            <div class="space-y-4 rounded-xl bg-surface-card p-6 shadow-sm lg:sticky lg:top-28">
                @if ($event->label)
                    <span class="inline-block rounded-full bg-gold-light px-3 py-1 text-xs font-bold text-on-surface">{{ $event->label }}</span>
                @endif
                <dl class="space-y-4 text-sm">
                    <div class="flex gap-3">
                        <x-site.icon name="calendar_today" class="text-brand" />
                        <div>
                            <dt class="text-muted">{{ __('Tanggal') }}</dt>
                            <dd class="font-semibold text-on-surface">
                                {{ $event->starts_at->translatedFormat('l, d F Y') }}
                                @if ($event->ends_at && ! $event->ends_at->isSameDay($event->starts_at))
                                    – {{ $event->ends_at->translatedFormat('l, d F Y') }}
                                @endif
                            </dd>
                        </div>
                    </div>
                    @unless ($event->all_day)
                        <div class="flex gap-3">
                            <x-site.icon name="schedule" class="text-brand" />
                            <div>
                                <dt class="text-muted">{{ __('Waktu') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ $event->starts_at->format('H:i') }}@if ($event->ends_at) – {{ $event->ends_at->format('H:i') }}@endif WIB</dd>
                            </div>
                        </div>
                    @endunless
                    @if ($event->location)
                        <div class="flex gap-3">
                            <x-site.icon name="location_on" class="text-brand" />
                            <div>
                                <dt class="text-muted">{{ __('Tempat') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ $event->location }}</dd>
                            </div>
                        </div>
                    @endif
                    @if ($event->audience)
                        <div class="flex gap-3">
                            <x-site.icon name="person" class="text-brand" />
                            <div>
                                <dt class="text-muted">{{ __('Peserta') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ $event->audience }}</dd>
                            </div>
                        </div>
                    @endif
                </dl>
                <a href="{{ route('site.events.index') }}" class="inline-flex items-center gap-1 pt-2 text-sm font-semibold text-brand"><x-site.icon name="arrow_back" class="text-[16px]!" /> {{ __('Semua agenda') }}</a>
            </div>
        </aside>
    </div>
</x-site.layout>
