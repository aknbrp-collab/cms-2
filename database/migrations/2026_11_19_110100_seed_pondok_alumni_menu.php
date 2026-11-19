<?php

use Database\Seeders\AlumniMenuSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Add the Pondok "Alumni" dropdown to an existing install (v3). Does
     * nothing on a fresh database without sites; the demo seeder adds it there.
     */
    public function up(): void
    {
        (new AlumniMenuSeeder)->run();
    }

    /**
     * The menu stays: admins may have edited it since.
     */
    public function down(): void {}
};
