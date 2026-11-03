@props(['items'])

{{-- Slide-in navigation for small screens. Toggled by the parent's `open` Alpine state. --}}
<div x-cloak x-show="open" class="fixed inset-0 z-[60] xl:hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/40" x-on:click="open = false" x-transition.opacity></div>
    <nav class="absolute inset-y-0 end-0 flex w-80 max-w-[85vw] flex-col overflow-y-auto bg-surface-card shadow-2xl" x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
        <div class="flex items-center justify-between border-b border-border-subtle px-5 py-4">
            <span class="font-semibold text-on-surface">{{ __('Menu') }}</span>
            <button type="button" class="rounded-lg p-1.5 text-on-surface-variant hover:bg-surface-low" x-on:click="open = false" aria-label="{{ __('Tutup menu') }}">
                <x-site.icon name="close" />
            </button>
        </div>
        <ul class="flex-1 space-y-1 p-3">
            <li><a href="{{ route('site.home') }}" class="block rounded-lg px-3 py-2.5 font-medium text-on-surface hover:bg-surface-low">{{ __('Beranda') }}</a></li>
            @foreach ($items as $item)
                <li x-data="{ sub: false }">
                    @if ($item->children->isNotEmpty())
                        <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 font-medium text-on-surface hover:bg-surface-low" x-on:click="sub = ! sub" :aria-expanded="sub">
                            {{ $item->label }} <x-site.icon name="expand_more" class="transition" x-bind:class="sub && 'rotate-180'" />
                        </button>
                        <ul x-show="sub" x-transition class="ms-3 space-y-1 border-s border-border-subtle ps-3">
                            @foreach ($item->children as $child)
                                <li><a href="{{ $child->href() }}" @if ($child->open_in_new_tab) target="_blank" rel="noopener" @endif class="block rounded-lg px-3 py-2 text-sm text-on-surface-variant hover:bg-surface-low hover:text-brand">{{ $child->label }}</a></li>
                            @endforeach
                        </ul>
                    @else
                        <a href="{{ $item->href() }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener" @endif class="block rounded-lg px-3 py-2.5 font-medium text-on-surface hover:bg-surface-low">{{ $item->label }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
        {{ $slot }}
    </nav>
</div>
