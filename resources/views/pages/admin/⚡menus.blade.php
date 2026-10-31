<?php

use App\Enums\MenuItemType;
use App\Enums\MenuLocation;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Menus')] class extends Component {
    #[Url]
    public string $location = 'header';

    public ?string $editingId = null;

    public string $label = '';

    public string $type = 'url';

    public string $url = '';

    public ?string $linkableId = null;

    public ?string $parentId = null;

    public bool $openInNewTab = false;

    public function mount(): void
    {
        $this->authorize('menus.view');
    }

    #[Computed]
    public function menu(): ?Menu
    {
        $site = current_site();
        $location = MenuLocation::tryFrom($this->location) ?? MenuLocation::Header;

        return $site ? Menu::for($site, $location) : null;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function rootItems(): Collection
    {
        return $this->menu?->rootItems()->with(['children.linkable', 'linkable'])->get() ?? new Collection;
    }

    /**
     * @return Collection<int, Page>
     */
    #[Computed]
    public function pages(): Collection
    {
        return Page::query()->orderBy('title')->get(['id', 'title', 'slug']);
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get(['id', 'name', 'slug']);
    }

    public function create(?string $parentId = null): void
    {
        $this->authorize('menus.update');
        $this->resetForm();
        $this->parentId = $parentId;
        Flux::modal('menu-item-form')->show();
    }

    public function edit(string $id): void
    {
        $this->authorize('menus.update');
        $item = $this->findItem($id);

        $this->resetForm();
        $this->editingId = $item->id;
        $this->label = $item->label;
        $this->type = $item->type->value;
        $this->url = (string) $item->url;
        $this->linkableId = $item->linkable_id;
        $this->parentId = $item->parent_id;
        $this->openInNewTab = $item->open_in_new_tab;

        Flux::modal('menu-item-form')->show();
    }

    public function save(): void
    {
        $this->authorize('menus.update');
        $menu = $this->menu;
        abort_if($menu === null, 404);

        $type = MenuItemType::tryFrom($this->type);

        $this->validate([
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(MenuItemType::class)],
            'url' => [Rule::requiredIf($type === MenuItemType::Url), 'nullable', 'string', 'max:2048', 'regex:~^(https?://|/|#|mailto:|tel:)~'],
            'linkableId' => [
                Rule::requiredIf($type !== MenuItemType::Url),
                'nullable',
                match ($type) {
                    MenuItemType::Page => Rule::exists('pages', 'id')->where('site_id', $menu->site_id),
                    MenuItemType::Category => Rule::exists('categories', 'id')->where('site_id', $menu->site_id),
                    default => 'prohibited',
                },
            ],
            'parentId' => ['nullable', Rule::exists('menu_items', 'id')->where('menu_id', $menu->id)->whereNull('parent_id')],
            'openInNewTab' => ['boolean'],
        ], ['url.regex' => __('Start the link with https://, / or #.')]);

        $item = $this->editingId ? $this->findItem($this->editingId) : new MenuItem(['menu_id' => $menu->id]);

        if ($this->editingId && $this->parentId === $item->id) {
            $this->addError('parentId', __('An item cannot be its own parent.'));

            return;
        }

        $parentChanged = ! $item->exists || $item->parent_id !== ($this->parentId ?: null);

        $item->fill([
            'label' => $this->label,
            'type' => $type,
            'url' => $type === MenuItemType::Url ? $this->url : null,
            'linkable_type' => match ($type) {
                MenuItemType::Page => 'page',
                MenuItemType::Category => 'category',
                default => null,
            },
            'linkable_id' => $type === MenuItemType::Url ? null : $this->linkableId,
            'parent_id' => $this->parentId ?: null,
            'open_in_new_tab' => $this->openInNewTab,
        ]);

        if ($parentChanged) {
            $item->sort_order = (int) $menu->items()->where('parent_id', $item->parent_id)->max('sort_order') + 1;
        }

        $item->save();
        // A parent that becomes a child would leave its own children three levels deep.
        $item->children()->update(['parent_id' => $item->parent_id === null ? $item->id : $item->parent_id]);

        $this->refreshItems();
        Flux::modal('menu-item-form')->close();
        Flux::toast(variant: 'success', text: __('Menu saved.'));
        $this->resetForm();
    }

    public function move(string $id, int $direction): void
    {
        $this->authorize('menus.update');
        $item = $this->findItem($id);

        $siblings = MenuItem::query()
            ->where('menu_id', $item->menu_id)
            ->where('parent_id', $item->parent_id)
            ->orderBy('sort_order')
            ->get()
            ->values();

        $index = $siblings->search(fn (MenuItem $sibling) => $sibling->is($item));
        $target = $siblings->get($index + $direction);

        if ($target === null) {
            return;
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$index + $direction]] = [$ordered[$index + $direction], $ordered[$index]];

        foreach ($ordered as $position => $sibling) {
            $sibling->update(['sort_order' => $position]);
        }

        $this->refreshItems();
    }

    public function delete(string $id): void
    {
        $this->authorize('menus.update');
        $this->findItem($id)->delete();
        $this->refreshItems();

        Flux::toast(text: __('Menu item deleted.'));
    }

    protected function findItem(string $id): MenuItem
    {
        return MenuItem::query()->where('menu_id', $this->menu?->id)->findOrFail($id);
    }

    protected function refreshItems(): void
    {
        unset($this->rootItems);
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'label', 'type', 'url', 'linkableId', 'parentId', 'openInNewTab');
        $this->resetValidation();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Menus') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Navigation links of the public website. Up to two levels.') }}</flux:text>
        </div>
        @if (current_site())
            @can('menus.update')
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add link') }}</flux:button>
            @endcan
        @endif
    </div>

    @if (! current_site())
        <x-admin.needs-site />
    @else
        <flux:navbar>
            @foreach (MenuLocation::cases() as $case)
                <flux:navbar.item as="button" type="button" wire:click="$set('location', '{{ $case->value }}')" :current="$location === $case->value">{{ $case->label() }}</flux:navbar.item>
            @endforeach
        </flux:navbar>

        <flux:card class="max-w-3xl">
            @forelse ($this->rootItems as $item)
                <div wire:key="item-{{ $item->id }}" class="border-b border-zinc-100 py-2 last:border-0 dark:border-zinc-700">
                    @include('pages.admin.partials.menu-item-row', ['item' => $item, 'first' => $loop->first, 'last' => $loop->last, 'child' => false])

                    @foreach ($item->children as $child)
                        <div wire:key="item-{{ $child->id }}" class="ms-8 mt-1">
                            @include('pages.admin.partials.menu-item-row', ['item' => $child, 'first' => $loop->first, 'last' => $loop->last, 'child' => true])
                        </div>
                    @endforeach
                </div>
            @empty
                <flux:text>{{ __('This menu is empty.') }}</flux:text>
            @endforelse
        </flux:card>
    @endif

    <flux:modal name="menu-item-form" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit link') : __('Add link') }}</flux:heading>

            <flux:input wire:model="label" :label="__('Label')" required />

            <flux:select wire:model.live="type" :label="__('Links to')">
                @foreach (MenuItemType::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </flux:select>

            @if ($type === 'url')
                <flux:input wire:model="url" :label="__('URL')" placeholder="https://… or /path" />
            @elseif ($type === 'page')
                <flux:select wire:model="linkableId" :label="__('Page')">
                    <option value="">{{ __('Choose a page') }}</option>
                    @foreach ($this->pages as $page)
                        <option value="{{ $page->id }}">{{ $page->title }}</option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="linkableId" :label="__('Category')">
                    <option value="">{{ __('Choose a category') }}</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model="parentId" :label="__('Parent')">
                <option value="">{{ __('None (top level)') }}</option>
                @foreach ($this->rootItems as $root)
                    @if ($root->id !== $editingId)
                        <option value="{{ $root->id }}">{{ $root->label }}</option>
                    @endif
                @endforeach
            </flux:select>

            <flux:checkbox wire:model="openInNewTab" :label="__('Open in a new tab')" />

            <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
