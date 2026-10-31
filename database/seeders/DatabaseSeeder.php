<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SiteSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        // Local superadmin. In production, create users with `php artisan cms:create-superadmin`.
        if (! app()->isProduction()) {
            User::factory()
                ->create(['name' => 'Superadmin', 'email' => 'superadmin@pondok.test'])
                ->assignRole(Role::Superadmin->value);
        }
    }
}
