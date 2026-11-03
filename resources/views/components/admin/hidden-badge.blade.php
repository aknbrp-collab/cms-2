@props(['item'])

{{-- Small badge for items hidden from the public site. --}}
@if ($item->is_hidden)
    <flux:badge size="sm" color="amber" icon="eye-slash" {{ $attributes }}>{{ __('Hidden') }}</flux:badge>
@endif
