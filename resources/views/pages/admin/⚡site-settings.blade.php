<?php

use App\Models\Site;
use App\Support\Embed;
use App\Support\SvgSanitizer;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Site settings')] class extends Component {
    use WithFileUploads;

    #[Url]
    public string $tab = 'identity';

    public string $name = '';

    /**
     * Flat dot-notation settings, see Site::SETTING_DEFAULTS. Dots are
     * replaced with "__" so Livewire can bind them.
     *
     * @var array<string, mixed>
     */
    public array $settings = [];

    /** @var array<string, UploadedFile|null> */
    public array $branding = [];

    public function mount(): void
    {
        $this->authorize('sites.update');

        if ($this->site) {
            $this->name = $this->site->name;
            $this->settings = collect($this->site->allSettings())
                ->mapWithKeys(fn ($value, $key) => [str_replace('.', '__', $key) => $value])
                ->all();
        }
    }

    #[Computed]
    public function site(): ?Site
    {
        return current_site();
    }

    public function save(): void
    {
        $this->authorize('sites.update');
        $site = $this->site;
        abort_if($site === null, 404);

        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'settings.identity__tagline' => ['nullable', 'string', 'max:255'],
            'settings.identity__description' => ['nullable', 'string', 'max:1000'],
            'settings.contact__phone' => ['nullable', 'string', 'max:50'],
            'settings.contact__whatsapp' => ['nullable', 'string', 'max:50'],
            'settings.contact__email' => ['nullable', 'email', 'max:255'],
            'settings.contact__address' => ['nullable', 'string', 'max:1000'],
            'settings.contact__map_embed_url' => ['nullable', 'url', function ($attribute, $value, $fail) {
                if (filled($value) && ! Embed::isAllowed($value)) {
                    $fail(__('Use a Google Maps "embed a map" link.'));
                }
            }],
            'settings.social__facebook' => ['nullable', 'url', 'max:255'],
            'settings.social__instagram' => ['nullable', 'url', 'max:255'],
            'settings.social__youtube' => ['nullable', 'url', 'max:255'],
            'settings.social__tiktok' => ['nullable', 'url', 'max:255'],
            'settings.theme__primary' => $hex,
            'settings.theme__primary_container' => $hex,
            'settings.theme__secondary' => $hex,
            'settings.header__announcement' => ['nullable', 'string', 'max:255'],
            'settings.header__cta_label' => ['nullable', 'string', 'max:50'],
            'settings.header__cta_url' => ['nullable', 'string', 'max:2048'],
            'settings.footer__text' => ['nullable', 'string', 'max:2000'],
            'settings.analytics__ga_measurement_id' => ['nullable', 'regex:/^G-[A-Z0-9]+$/'],
            'settings.analytics__head_code' => ['nullable', 'string', 'max:20000'],
            'settings.analytics__body_code' => ['nullable', 'string', 'max:20000'],
            'settings.prayer__enabled' => ['boolean'],
            'settings.prayer__latitude' => ['nullable', 'required_if_accepted:settings.prayer__enabled', 'numeric', 'between:-90,90'],
            'settings.prayer__longitude' => ['nullable', 'required_if_accepted:settings.prayer__enabled', 'numeric', 'between:-180,180'],
            'settings.prayer__method' => ['required', 'in:kemenag,mwl,umm_al_qura,egyptian'],
            'branding.logo' => ['nullable', 'image', 'max:4096'],
            'branding.logo_dark' => ['nullable', 'image', 'max:4096'],
            'branding.calligraphy' => ['nullable', 'file', 'mimes:png,svg,webp', 'max:4096'],
            'branding.calligraphy_dark' => ['nullable', 'file', 'mimes:png,svg,webp', 'max:4096'],
            'branding.favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:1024'],
            'branding.og_default' => ['nullable', 'image', 'max:4096'],
        ]);

        foreach (array_filter($this->branding) as $collection => $file) {
            SvgSanitizer::clean($file, "branding.{$collection}");
        }

        $site->name = $this->name;
        $site->fillSettings(collect($this->settings)
            ->mapWithKeys(fn ($value, $key) => [str_replace('__', '.', $key) => $value === '' ? null : $value])
            ->all());
        $site->save();

        foreach (array_filter($this->branding) as $collection => $file) {
            if (in_array($collection, Site::BRANDING_COLLECTIONS, true)) {
                $site->addMedia($file)->toMediaCollection($collection);
            }
        }

        $this->reset('branding');
        unset($this->site);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    public function removeBranding(string $collection): void
    {
        $this->authorize('sites.update');
        abort_unless(in_array($collection, Site::BRANDING_COLLECTIONS, true), 404);

        $this->site?->clearMediaCollection($collection);
        unset($this->site);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Site settings') }}</flux:heading>
        <flux:text class="mt-1">{{ $this->site?->name }} · {{ $this->site?->domain }}</flux:text>
    </div>

    @if (! $this->site)
        <x-admin.needs-site />
    @else
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:navbar class="-mb-2 overflow-x-auto">
                @foreach (['identity' => __('Identity'), 'contact' => __('Contact & social'), 'theme' => __('Theme'), 'layout' => __('Header & footer'), 'analytics' => __('Analytics'), 'prayer' => __('Prayer times')] as $key => $label)
                    <flux:navbar.item as="button" type="button" wire:click="$set('tab', '{{ $key }}')" :current="$tab === $key">{{ $label }}</flux:navbar.item>
                @endforeach
            </flux:navbar>

            <flux:card class="max-w-3xl space-y-5">
                @if ($tab === 'identity')
                    <flux:input wire:model="name" :label="__('Site name')" required />
                    <flux:input wire:model="settings.identity__tagline" :label="__('Tagline')" />
                    <flux:textarea wire:model="settings.identity__description" :label="__('Short description')" rows="3" :description="__('Used as the default meta description.')" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach (['logo' => __('Logo'), 'logo_dark' => __('Logo for dark mode (optional)'), 'calligraphy' => __('Name in Arabic calligraphy'), 'calligraphy_dark' => __('Calligraphy for dark mode (optional)'), 'favicon' => __('Favicon'), 'og_default' => __('Default social share image')] as $collection => $label)
                            <flux:field wire:key="branding-{{ $collection }}">
                                <flux:label>{{ $label }}</flux:label>
                                @if ($url = $this->site->brandingUrl($collection))
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $url }}" alt="" @class(['h-14 max-w-40 rounded border border-zinc-200 object-contain p-1 dark:border-zinc-700', 'bg-zinc-800' => str_ends_with($collection, '_dark'), 'bg-zinc-50' => ! str_ends_with($collection, '_dark')])>
                                        <flux:button size="xs" variant="ghost" wire:click="removeBranding('{{ $collection }}')">{{ __('Remove') }}</flux:button>
                                    </div>
                                @endif
                                <flux:input type="file" wire:model="branding.{{ $collection }}" />
                                <flux:error name="branding.{{ $collection }}" />
                            </flux:field>
                        @endforeach
                    </div>
                @elseif ($tab === 'contact')
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="settings.contact__phone" :label="__('Phone')" />
                        <flux:input wire:model="settings.contact__whatsapp" :label="__('WhatsApp')" />
                        <flux:input wire:model="settings.contact__email" type="email" :label="__('Email')" />
                    </div>
                    <flux:textarea wire:model="settings.contact__address" :label="__('Address')" rows="3" />
                    <flux:input wire:model="settings.contact__map_embed_url" :label="__('Google Maps embed URL')" :description="__('Google Maps → Share → Embed a map → copy the src link.')" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="settings.social__facebook" :label="__('Facebook URL')" />
                        <flux:input wire:model="settings.social__instagram" :label="__('Instagram URL')" />
                        <flux:input wire:model="settings.social__youtube" :label="__('YouTube URL')" />
                        <flux:input wire:model="settings.social__tiktok" :label="__('TikTok URL')" />
                    </div>
                @elseif ($tab === 'theme')
                    <flux:text>{{ __('Brand colours of the public website. Other shades come from the site theme.') }}</flux:text>
                    <div class="grid gap-5 sm:grid-cols-3">
                        @foreach (['primary' => __('Primary'), 'primary_container' => __('Primary (light)'), 'secondary' => __('Accent / gold')] as $key => $label)
                            <flux:field wire:key="color-{{ $key }}">
                                <flux:label>{{ $label }}</flux:label>
                                <div class="flex items-center gap-2">
                                    <input type="color" wire:model.live="settings.theme__{{ $key }}" class="h-10 w-12 cursor-pointer rounded border border-zinc-200 dark:border-zinc-700">
                                    <flux:input wire:model.live="settings.theme__{{ $key }}" class="font-mono" />
                                </div>
                                <flux:error name="settings.theme__{{ $key }}" />
                            </flux:field>
                        @endforeach
                    </div>
                    <div class="flex overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="h-12 flex-1" style="background: {{ $settings['theme__primary'] ?? '' }}"></div>
                        <div class="h-12 flex-1" style="background: {{ $settings['theme__primary_container'] ?? '' }}"></div>
                        <div class="h-12 flex-1" style="background: {{ $settings['theme__secondary'] ?? '' }}"></div>
                    </div>
                @elseif ($tab === 'layout')
                    <flux:input wire:model="settings.header__announcement" :label="__('Top bar announcement')" :description="__('Short text shown above the header, e.g. admissions are open.')" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="settings.header__cta_label" :label="__('Header button label')" />
                        <flux:input wire:model="settings.header__cta_url" :label="__('Header button link')" />
                    </div>
                    <flux:textarea wire:model="settings.footer__text" :label="__('Footer text')" rows="4" />
                    <flux:text size="sm">{{ __('Navigation links are managed under Menus.') }}</flux:text>
                @elseif ($tab === 'analytics')
                    <flux:input wire:model="settings.analytics__ga_measurement_id" :label="__('Google Analytics measurement ID')" placeholder="G-XXXXXXXXXX" />
                    <flux:callout icon="exclamation-triangle" color="amber" :text="__('Code below is inserted into every public page as-is. Only paste snippets from trusted providers.')" />
                    <flux:textarea wire:model="settings.analytics__head_code" :label="__('Extra code in <head>')" rows="5" class="font-mono text-xs" />
                    <flux:textarea wire:model="settings.analytics__body_code" :label="__('Extra code at end of <body>')" rows="5" class="font-mono text-xs" />
                @elseif ($tab === 'prayer')
                    <flux:switch wire:model.live="settings.prayer__enabled" :label="__('Show Hijri date and prayer times in the top bar')" />
                    <div class="grid gap-5 sm:grid-cols-3">
                        <flux:input wire:model="settings.prayer__latitude" :label="__('Latitude')" placeholder="-7.0" />
                        <flux:input wire:model="settings.prayer__longitude" :label="__('Longitude')" placeholder="110.4" />
                        <flux:select wire:model="settings.prayer__method" :label="__('Calculation method')">
                            <option value="kemenag">Kemenag RI</option>
                            <option value="mwl">Muslim World League</option>
                            <option value="umm_al_qura">Umm al-Qura</option>
                            <option value="egyptian">{{ __('Egyptian General Authority') }}</option>
                        </flux:select>
                    </div>
                @endif
            </flux:card>

            @if ($errors->any())
                <flux:callout icon="exclamation-circle" color="red" class="max-w-3xl" :heading="__('Some fields need attention')">
                    <flux:callout.text>{{ implode(' ', $errors->all()) }}</flux:callout.text>
                </flux:callout>
            @endif

            <div>
                <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
            </div>
        </form>
    @endif
</div>
