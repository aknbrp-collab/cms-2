@props(['model' => 'form.seo'])

{{-- SEO overrides. Empty fields fall back to the content title, excerpt and image. --}}
<flux:card class="space-y-4">
    <flux:heading>{{ __('SEO') }}</flux:heading>

    <flux:input wire:model="{{ $model }}.meta_title" :label="__('Meta title')" maxlength="255" :description="__('Defaults to the title.')" />
    <flux:textarea wire:model="{{ $model }}.meta_description" :label="__('Meta description')" rows="3" maxlength="500" />
    <livewire:admin.media-picker wire:model="{{ $model }}.og_image_id" :label="__('Social share image')" />
    <flux:input wire:model="{{ $model }}.canonical_url" type="url" :label="__('Canonical URL')" />
    <flux:checkbox wire:model="{{ $model }}.noindex" :label="__('Hide from search engines (noindex)')" />
</flux:card>
