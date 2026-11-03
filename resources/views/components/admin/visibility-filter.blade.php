{{-- Visibility filter for list screens using App\Livewire\Concerns\TogglesVisibility. --}}
<flux:select wire:model.live="visibility" {{ $attributes->class('max-w-44') }}>
    <option value="">{{ __('Visible and hidden') }}</option>
    <option value="visible">{{ __('Visible only') }}</option>
    <option value="hidden">{{ __('Hidden only') }}</option>
</flux:select>
