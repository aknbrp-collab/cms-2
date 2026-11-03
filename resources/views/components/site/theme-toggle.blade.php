{{-- Light/dark switch for the public header. State lives on <html class="dark">, see sites/partials/head. --}}
<button
    type="button"
    x-data="{ dark: window.siteTheme?.isDark() ?? false }"
    x-on:click="dark = window.siteTheme.toggle()"
    x-bind:aria-pressed="dark"
    aria-label="{{ __('Mode gelap') }}"
    title="{{ __('Ganti mode terang/gelap') }}"
    {{ $attributes->class('rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-surface-low hover:text-on-surface') }}
>
    {{-- Wrappers carry the visibility: the icon font's own CSS sets display on the icon itself. --}}
    <span class="flex dark:hidden"><x-site.icon name="dark_mode" /></span>
    <span class="hidden dark:flex"><x-site.icon name="light_mode" /></span>
</button>
