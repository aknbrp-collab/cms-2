<?php

namespace Database\Seeders;

use App\Enums\Role as CmsRole;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Sync every permission and role, and each role's permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Permission::query()->whereNotIn('name', Permissions::all())->delete();

        // Role syncing looks permissions up through the cache, so drop the stale copy.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (CmsRole::cases() as $cmsRole) {
            Role::findOrCreate($cmsRole->value, 'web')->syncPermissions($cmsRole->permissions());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
