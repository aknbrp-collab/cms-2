{{-- One row of the menu builder. Expects $item, $first, $last and $child. --}}
<div @class(['flex items-center justify-between gap-3', 'opacity-60' => $item->is_hidden])>
    <div class="min-w-0">
        <div class="flex items-center gap-2">
            <span class="font-medium">{{ $item->label }}</span>
            <x-admin.hidden-badge :item="$item" />
            @if ($item->open_in_new_tab)
                <flux:icon.arrow-top-right-on-square class="size-4 text-zinc-400" />
            @endif
        </div>
        <div class="truncate font-mono text-xs text-zinc-500">{{ $item->type->label() }} · {{ $item->href() }}</div>
    </div>

    @can('menus.update')
        <div class="flex shrink-0 gap-1">
            @unless ($child)
                <flux:button size="xs" variant="ghost" icon="plus" wire:click="create('{{ $item->id }}')" :tooltip="__('Add sub-link')" />
            @endunless
            <x-admin.visibility-toggle :item="$item" size="xs" />
            <flux:button size="xs" variant="ghost" icon="arrow-up" wire:click="move('{{ $item->id }}', -1)" :disabled="$first" />
            <flux:button size="xs" variant="ghost" icon="arrow-down" wire:click="move('{{ $item->id }}', 1)" :disabled="$last" />
            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit('{{ $item->id }}')" />
            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete('{{ $item->id }}')" wire:confirm="{{ __('Delete this link and its sub-links?') }}" />
        </div>
    @endcan
</div>
