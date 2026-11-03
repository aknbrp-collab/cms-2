{{-- Accordion of FAQ items. Expects $faqs. --}}
<div class="divide-y divide-border-subtle overflow-hidden rounded-xl bg-surface-card shadow-sm">
    @foreach ($faqs as $faq)
        <details class="group">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-on-surface hover:text-brand">
                {{ $faq->question }}
                <x-site.icon name="expand_more" class="shrink-0 transition group-open:rotate-180" />
            </summary>
            {{-- Sanitised by App\Support\RichText when saved. --}}
            <div class="prose-site px-5 pb-5">{!! $faq->answer !!}</div>
        </details>
    @endforeach
</div>
