<?php

use App\Models\Category;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categories')] class extends Component {
    public ?string $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public bool $showOnHome = false;

    public int $homeOrder = 0;

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->withCount('posts')->orderByDesc('show_on_home')->orderBy('home_order')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('categories.create');
        $this->resetForm();
        Flux::modal('category-form')->show();
    }

    public function edit(string $id): void
    {
        $this->authorize('categories.update');
        $category = Category::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = (string) $category->description;
        $this->showOnHome = $category->show_on_home;
        $this->homeOrder = $category->home_order;

        Flux::modal('category-form')->show();
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'categories.update' : 'categories.create');
        $site = current_site();
        abort_if($site === null, 404);

        $this->slug = Str::slug($this->slug ?: $this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('categories')->where('site_id', $site->id)->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'showOnHome' => ['boolean'],
            'homeOrder' => ['integer', 'min:0', 'max:999'],
        ]);

        $category = $this->editingId ? Category::query()->findOrFail($this->editingId) : new Category;
        $category->fill([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'show_on_home' => $validated['showOnHome'],
            'home_order' => $validated['homeOrder'],
        ])->save();

        Flux::modal('category-form')->close();
        Flux::toast(variant: 'success', text: __('Category saved.'));
        $this->resetForm();
    }

    public function toggleHidden(string $id): void
    {
        $this->authorize('categories.update');
        $hidden = Visibility::toggle(Category::query()->findOrFail($id));
        unset($this->categories);

        Flux::toast(variant: 'success', text: $hidden ? __('Category hidden. Its page and menu links are gone; its posts stay visible.') : __('Shown on the website again.'));
    }

    public function delete(string $id): void
    {
        $this->authorize('categories.delete');
        Category::query()->findOrFail($id)->delete();

        Flux::toast(text: __('Category deleted. Its posts are now uncategorised.'));
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'name', 'slug', 'description', 'showOnHome', 'homeOrder');
        $this->resetValidation();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Categories') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Group posts. Categories shown on the homepage become homepage sections.') }}</flux:text>
        </div>
        @if (current_site())
            @can('categories.create')
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New category') }}</flux:button>
            @endcan
        @endif
    </div>

    @if (! current_site())
        <x-admin.needs-site />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Slug') }}</flux:table.column>
                <flux:table.column>{{ __('Posts') }}</flux:table.column>
                <flux:table.column>{{ __('Homepage') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->categories as $category)
                    <flux:table.row :key="$category->id">
                        <flux:table.cell class="font-medium">{{ $category->name }} <x-admin.hidden-badge :item="$category" class="ms-1" /></flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $category->slug }}</flux:table.cell>
                        <flux:table.cell>{{ $category->posts_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($category->show_on_home)
                                <flux:badge size="sm" color="emerald">{{ __('Section #:n', ['n' => $category->home_order]) }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <x-admin.visibility-toggle :item="$category" :can="auth()->user()->can('categories.update')" />
                            @can('categories.update')
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $category->id }}')" />
                            @endcan
                            @can('categories.delete')
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete('{{ $category->id }}')" wire:confirm="{{ __('Delete this category?') }}" />
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('No categories yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="category-form" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit category') : __('New category') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="slug" :label="__('Slug')" :description="__('Leave empty to generate from the name.')" />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />
            <flux:switch wire:model="showOnHome" :label="__('Show as a homepage section')" />
            <flux:input wire:model="homeOrder" type="number" min="0" :label="__('Homepage order')" />
            <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
