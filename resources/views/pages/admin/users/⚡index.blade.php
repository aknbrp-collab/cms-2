<?php

use App\Enums\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?string $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $isActive = true;

    /** @var list<string> */
    public array $roles = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->whereLike('name', "%{$this->search}%")
                    ->orWhereLike('email', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Roles the current user may grant.
     *
     * @return list<Role>
     */
    #[Computed]
    public function assignableRoles(): array
    {
        if (! Auth::user()->can('roles.assign')) {
            return [];
        }

        return array_values(array_filter(
            Role::cases(),
            fn (Role $role) => $role !== Role::Superadmin || Auth::user()->isSuperadmin(),
        ));
    }

    public function create(): void
    {
        $this->authorize('users.create');

        $this->resetForm();
        Flux::modal('user-form')->show();
    }

    public function edit(string $id): void
    {
        $this->authorize('users.update');

        $user = User::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->isActive = $user->is_active;
        $this->roles = $user->getRoleNames()->all();

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'users.update' : 'users.create');

        $assignable = array_map(fn (Role $role) => $role->value, $this->assignableRoles);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', Password::default()],
            'isActive' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => [Rule::in($assignable)],
        ]);

        $user = $this->editingId ? User::query()->findOrFail($this->editingId) : new User;

        if ($user->is(Auth::user()) && ! $validated['isActive']) {
            $this->addError('isActive', __('You cannot deactivate your own account.'));

            return;
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['isActive'],
        ]);

        if (filled($validated['password'])) {
            $user->password = $validated['password'];
        }

        if (! $user->exists) {
            $user->email_verified_at = now();
        }

        $user->save();

        if (Auth::user()->can('roles.assign')) {
            // Keep roles the current user may not grant (e.g. superadmin) untouched.
            $kept = $user->getRoleNames()->diff($assignable)->all();
            $user->syncRoles([...$kept, ...$validated['roles']]);
        }

        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: __('User saved.'));
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'password', 'isActive', 'roles');
        $this->resetValidation();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>
            <flux:text class="mt-1">{{ __('CMS accounts and their roles.') }}</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search name or email')" icon="magnifying-glass" />
            @can('users.create')
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New user') }}</flux:button>
            @endcan
        </div>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Roles') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell class="font-medium">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($user->cmsRoles() as $role)
                                <flux:badge size="sm">{{ $role->label() }}</flux:badge>
                            @endforeach
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($user->is_active)
                            <flux:badge size="sm" color="emerald">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @can('users.update')
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $user->id }}')" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="user-form" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit user') : __('New user') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" required />
            <flux:input
                wire:model="password"
                type="password"
                :label="__('Password')"
                :description="$editingId ? __('Leave blank to keep the current password.') : null"
                viewable
            />

            @if ($this->assignableRoles !== [])
                <flux:checkbox.group wire:model="roles" :label="__('Roles')">
                    @foreach ($this->assignableRoles as $role)
                        <flux:checkbox
                            :value="$role->value"
                            :label="$role->label()"
                            :description="$role->site() ? __('Only :site', ['site' => $role->site()->label()]) : __('All sites')"
                        />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <flux:switch wire:model="isActive" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
