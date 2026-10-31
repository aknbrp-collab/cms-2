<?php

use App\Cms\Field;
use App\Cms\AdminResource;
use App\Cms\Resources;
use App\Livewire\ReferenceList;
use App\Support\RichText;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * Generic list + modal editor for the simple resources in App\Cms\Resources.
 */
new class extends ReferenceList {
    #[Locked]
    public string $resourceKey = '';

    #[Url]
    public string $search = '';

    public ?string $editingId = null;

    /** @var array<string, mixed> Form values keyed by field name. */
    public array $data = [];

    /** Bumped on every form reset so rich text editors and pickers re-mount. */
    public int $formVersion = 0;

    public function mount(string $resource): void
    {
        abort_if(Resources::find($resource) === null, 404);

        $this->resourceKey = $resource;
        $this->authorize($resource.'.view');
        $this->resetForm();
    }

    #[Computed]
    public function resource(): AdminResource
    {
        return Resources::find($this->resourceKey) ?? abort(404);
    }

    protected function module(): string
    {
        return $this->resourceKey;
    }

    protected function findItem(string $id): Model
    {
        return $this->resource->model()::query()->with('site')->findOrFail($id);
    }

    /**
     * @return Collection<int, Model>
     */
    #[Computed]
    public function items(): Collection
    {
        $resource = $this->resource;
        $query = $resource->model()::query()->with(['site', ...$resource->with()]);

        if ($this->search !== '') {
            $query->whereLike($resource->titleField(), "%{$this->search}%");
        }

        return $resource->order($query)->get();
    }

    public function create(): void
    {
        $this->authorizeCreate();
        $this->resetForm();
        Flux::modal('resource-form')->show();
    }

    public function edit(string $id): void
    {
        $item = $this->findItem($id);
        $this->authorizeItem($item, 'update');

        $this->resetForm();
        $this->editingId = $item->getKey();

        foreach ($this->resource->fields() as $field) {
            $value = $item->getAttribute($field->name);
            $this->data[$field->name] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                default => $value,
            };
        }

        Flux::modal('resource-form')->show();
    }

    public function save(): void
    {
        $resource = $this->resource;
        $modelClass = $resource->model();

        if ($this->editingId) {
            $item = $this->findItem($this->editingId);
            $this->authorizeItem($item, 'update');
        } else {
            $item = new $modelClass(['site_id' => $this->authorizeCreate()->id]);
        }

        $rules = [];
        foreach ($resource->fields() as $field) {
            $rules["data.{$field->name}"] = $field->validationRules();
        }

        $attributes = collect($resource->fields())->mapWithKeys(fn (Field $field) => ["data.{$field->name}" => Str::lower($field->label)])->all();
        $validated = $this->validate($rules, [], $attributes)['data'];

        foreach ($resource->fields() as $field) {
            $value = $validated[$field->name] ?? null;
            $value = $value === '' ? null : $value;

            $item->setAttribute($field->name, match ($field->type) {
                'richtext' => RichText::clean($value) ?: null,
                'number' => (int) $value,
                default => $value,
            });
        }

        if ($resource->hasSlug()) {
            $slug = Str::slug((string) $item->getAttribute($resource->titleField()));
            $item->setAttribute('slug', $this->uniqueSlug($item, $slug));
        }

        $item->save();

        Flux::modal('resource-form')->close();
        Flux::toast(variant: 'success', text: __('Saved.'));
        $this->resetForm();
        unset($this->items);
    }

    public function delete(string $id): void
    {
        $item = $this->findItem($id);
        $this->authorizeItem($item, 'delete');
        $item->delete();

        unset($this->items);
        Flux::toast(text: __('Deleted.'));
    }

    protected function uniqueSlug(Model $item, string $slug): string
    {
        $slug = $slug !== '' ? $slug : Str::lower(Str::random(8));
        $candidate = $slug;
        $suffix = 2;

        while ($item->newQuery()->withoutGlobalScopes()
            ->where('site_id', $item->getAttribute('site_id'))
            ->where('slug', $candidate)
            ->when($item->exists, fn ($query) => $query->whereKeyNot($item->getKey()))
            ->exists()) {
            $candidate = "{$slug}-".$suffix++;
        }

        return $candidate;
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->data = collect(Resources::find($this->resourceKey)?->fields() ?? [])
            ->mapWithKeys(fn (Field $field) => [$field->name => $field->type === 'number' ? 0 : null])
            ->all();
        $this->resetValidation();
        $this->formVersion++;
    }
}; ?>

@php($resource = $this->resource)

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $resource->label() }}</flux:heading>
            <flux:text class="mt-1">{{ $resource->description() }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search')" icon="magnifying-glass" />
            @if (current_site())
                @can($resource->key().'.create')
                    <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New') }}</flux:button>
                @endcan
            @endif
        </div>
    </div>

    @if (! current_site())
        <x-admin.needs-site />
    @elseif (! current_site()->hasModule($resource->key()))
        <flux:callout icon="information-circle" color="amber" :text="__('This module is not shown on :site.', ['site' => current_site()->name])" />
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ $resource->singularLabel() }}</flux:table.column>
            @unless (current_site())
                <flux:table.column>{{ __('Site') }}</flux:table.column>
            @endunless
            <flux:table.column>{{ __('Published') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="$item->getKey()">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            @if ($resource->thumbnailRelation() && ($thumb = $item->getRelationValue($resource->thumbnailRelation())))
                                <img src="{{ $thumb->url('thumb') }}" alt="" class="size-10 shrink-0 rounded object-cover">
                            @endif
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $item->getAttribute($resource->titleField()) }}</div>
                                @if ($subtitle = $resource->subtitle($item))
                                    <div class="truncate text-xs text-zinc-500">{{ $subtitle }}</div>
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>
                    @unless (current_site())
                        <flux:table.cell>{{ $item->site->name }}</flux:table.cell>
                    @endunless
                    <flux:table.cell>
                        @can($resource->key().'.publish')
                            <flux:switch :checked="$item->is_published" wire:click="togglePublished('{{ $item->getKey() }}')" />
                        @else
                            <flux:badge size="sm" :color="$item->is_published ? 'emerald' : 'zinc'">{{ $item->is_published ? __('Yes') : __('No') }}</flux:badge>
                        @endcan
                    </flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        @if ($action = $resource->rowAction())
                            <flux:button size="sm" variant="ghost" :href="route($action[1], $item)" wire:navigate>{{ $action[0] }}</flux:button>
                        @endif
                        @can($resource->key().'.update')
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $item->getKey() }}')" />
                        @endcan
                        @can($resource->key().'.delete')
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $item->getKey() }}')" wire:confirm="{{ __('Delete this item?') }}" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">{{ __('Nothing here yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="resource-form" class="w-full md:w-[44rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit :item', ['item' => Str::lower($resource->singularLabel())]) : __('New :item', ['item' => Str::lower($resource->singularLabel())]) }}</flux:heading>

            <div class="grid gap-5 sm:grid-cols-2" wire:key="resource-form-{{ $formVersion }}">
                @foreach ($resource->fields() as $field)
                    <div @class(['sm:col-span-2' => $field->span === 2])>
                        @switch($field->type)
                            @case('textarea')
                                <flux:textarea wire:model="data.{{ $field->name }}" :label="$field->label" :description="$field->help" rows="3" />
                                @break
                            @case('richtext')
                                <x-admin.rich-text model="data.{{ $field->name }}" :label="$field->label" />
                                @break
                            @case('image')
                                <livewire:admin.media-picker wire:model="data.{{ $field->name }}" :label="$field->label" :key="$field->name.'-'.$formVersion" />
                                @break
                            @case('select')
                                <flux:select wire:model="data.{{ $field->name }}" :label="$field->label">
                                    <option value="">{{ __('Choose…') }}</option>
                                    @foreach ($field->options as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </flux:select>
                                @break
                            @default
                                <flux:input wire:model="data.{{ $field->name }}" :type="$field->type" :label="$field->label" :description="$field->help" :required="$field->required" />
                        @endswitch
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
