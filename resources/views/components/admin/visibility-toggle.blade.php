@props(['item', 'can' => true, 'action' => null, 'size' => 'sm'])

{{-- Eye button that hides an item from the public site or shows it again. Calls toggleHidden('id') unless $action is given. --}}
@php
    $hidden = (bool) $item->is_hidden;
    $action ??= "toggleHidden('{$item->getKey()}')";
@endphp

@if ($can)
    <flux:button
        :size="$size"
        variant="ghost"
        :icon="$hidden ? 'eye-slash' : 'eye'"
        wire:click="{{ $action }}"
        :tooltip="$hidden ? __('Hidden. Show on the website') : __('Visible. Hide from the website')"
        :aria-label="$hidden ? __('Show on the website') : __('Hide from the website')"
        {{ $attributes->class($hidden ? 'text-amber-600! dark:text-amber-400!' : '') }}
    />
@elseif ($hidden)
    <flux:icon.eye-slash class="size-4 text-amber-600 dark:text-amber-400" />
@endif
