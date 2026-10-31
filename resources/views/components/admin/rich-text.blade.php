@props(['model', 'label' => null])

{{-- Trix editor bound to a Livewire property. The HTML is sanitised server side on save. --}}
<flux:field>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        x-data="{ value: $wire.entangle('{{ $model }}') }"
        x-on:trix-initialize="$event.target.editor.loadHTML(value ?? '')"
        x-on:trix-change="value = $event.target.value"
    >
        <trix-editor class="trix-content" {{ $attributes }}></trix-editor>
    </div>

    <flux:error :name="$model" />
</flux:field>
