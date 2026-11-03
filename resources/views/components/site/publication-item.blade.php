@props(['publication'])

<article class="rounded-xl border border-border-subtle bg-surface-card p-5 shadow-sm transition hover:shadow-md">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="rounded-full bg-mint px-2.5 py-0.5 font-bold text-brand">{{ $publication->type->label() }}</span>
        <span class="text-muted">{{ $publication->year }}</span>
        @if ($publication->journal)
            <span class="text-muted">· {{ $publication->journal->name }}</span>
        @endif
    </div>
    <h3 class="mt-2 leading-snug font-semibold text-on-surface hover:text-brand">
        <a href="{{ route('site.publications.show', $publication->slug) }}">{{ $publication->title }}</a>
    </h3>
    <p class="mt-1 text-sm text-brand">{{ $publication->authors }}</p>
    @if ($publication->abstract)
        <p class="mt-2 line-clamp-2 text-sm text-on-surface-variant">{{ $publication->abstract }}</p>
    @endif
</article>
