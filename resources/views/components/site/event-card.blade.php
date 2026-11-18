@props(['event'])

{{-- Agenda item with a calendar date badge. --}}
<article class="group site-card site-card-hover flex gap-5 p-5">
    <div class="flex w-16 shrink-0 flex-col items-center justify-center rounded-md bg-primary py-3 text-on-primary">
        <span class="text-2xl leading-none font-bold tabular-nums">{{ $event->starts_at->format('d') }}</span>
        <span class="mt-1 text-xs font-bold tracking-wide uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
        <span class="text-[10px] text-on-primary/75 tabular-nums">{{ $event->starts_at->format('Y') }}</span>
    </div>
    <div class="min-w-0 flex-1 space-y-1.5">
        @if ($event->label)
            <span class="site-chip">{{ $event->label }}</span>
        @endif
        <h3 class="font-bold leading-snug text-on-surface transition-colors group-hover:text-brand">
            <a href="{{ route('site.events.show', $event->slug) }}">{{ $event->title }}</a>
        </h3>
        @if ($event->summary)
            <p class="line-clamp-2 text-sm text-on-surface-variant">{{ $event->summary }}</p>
        @endif
        <div class="site-meta flex flex-wrap gap-x-4 gap-y-1 pt-1">
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
