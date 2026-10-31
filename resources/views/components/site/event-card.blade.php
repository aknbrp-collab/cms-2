@props(['event'])

{{-- Agenda item with a calendar date badge. --}}
<article class="group flex gap-5 rounded-xl border border-border-subtle bg-surface-card p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="flex w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-primary py-3 text-on-primary">
        <span class="text-2xl leading-none font-bold">{{ $event->starts_at->format('d') }}</span>
        <span class="mt-1 text-xs font-semibold tracking-wide uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
        <span class="text-[10px] text-on-primary/70">{{ $event->starts_at->format('Y') }}</span>
    </div>
    <div class="min-w-0 flex-1 space-y-1.5">
        @if ($event->label)
            <span class="inline-block rounded-full bg-gold-light px-2.5 py-0.5 text-[11px] font-bold text-on-surface">{{ $event->label }}</span>
        @endif
        <h3 class="leading-snug font-semibold text-on-surface group-hover:text-primary">
            <a href="{{ route('site.events.show', $event->slug) }}">{{ $event->title }}</a>
        </h3>
        @if ($event->summary)
            <p class="line-clamp-2 text-sm text-on-surface-variant">{{ $event->summary }}</p>
        @endif
        <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1 text-xs text-muted">
            @unless ($event->all_day)
                <span class="inline-flex items-center gap-1"><x-site.icon name="schedule" class="text-[14px]!" /> {{ $event->starts_at->format('H:i') }}@if ($event->ends_at) – {{ $event->ends_at->format('H:i') }}@endif WIB</span>
            @endunless
            @if ($event->location)
                <span class="inline-flex items-center gap-1"><x-site.icon name="location_on" class="text-[14px]!" /> {{ $event->location }}</span>
            @endif
            @if ($event->audience)
                <span class="inline-flex items-center gap-1"><x-site.icon name="person" class="text-[14px]!" /> {{ $event->audience }}</span>
            @endif
        </div>
    </div>
</article>
