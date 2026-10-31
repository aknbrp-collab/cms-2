<?php

use App\Enums\Role;
use App\Support\Permissions;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Roles & permissions')] class extends Component {
    /**
     * Permission names grouped by module.
     *
     * @return array<string, list<string>>
     */
    #[Computed]
    public function modules(): array
    {
        $modules = [];

        foreach (Permissions::all() as $name) {
            [$module, $action] = explode('.', $name, 2);
            $modules[$module][] = $action;
        }

        return $modules;
    }

    /**
     * @return array<string, list<string>>
     */
    #[Computed]
    public function grants(): array
    {
        $grants = [];

        foreach (Role::cases() as $role) {
            $grants[$role->value] = $role->permissions();
        }

        return $grants;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Roles & permissions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Roles are defined in code (App\\Enums\\Role) and synced by the RolesAndPermissionsSeeder.') }}</flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach (Role::cases() as $role)
            <flux:card wire:key="role-{{ $role->value }}">
                <flux:heading>{{ $role->label() }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Site scope') }}: {{ $role->site()?->label() ?? __('All sites') }}
                </flux:text>
            </flux:card>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th class="px-3 py-2 text-start font-medium">{{ __('Permission') }}</th>
                    @foreach (Role::cases() as $role)
                        <th class="px-3 py-2 text-center font-medium">{{ $role->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->modules as $module => $actions)
                    <tr class="bg-zinc-100/60 dark:bg-zinc-800">
                        <td colspan="{{ count(Role::cases()) + 1 }}" class="px-3 py-1.5 font-semibold">{{ str($module)->headline() }}</td>
                    </tr>
                    @foreach ($actions as $action)
                        <tr class="border-t border-zinc-100 dark:border-zinc-700" wire:key="perm-{{ $module }}-{{ $action }}">
                            <td class="px-3 py-1.5 ps-6 text-zinc-600 dark:text-zinc-300">{{ $action }}</td>
                            @foreach (Role::cases() as $role)
                                <td class="px-3 py-1.5 text-center">
                                    @if (in_array("{$module}.{$action}", $this->grants[$role->value], true))
                                        <flux:icon.check class="inline size-4 text-emerald-600" />
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
