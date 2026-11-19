<?php

use Database\Seeders\ProgramPendidikanSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Add the Pondok education units and their header dropdown to an existing
     * install (v3). Does nothing on a fresh database without sites; the demo
     * seeder adds them there.
     */
    public function up(): void
    {
        (new ProgramPendidikanSeeder)->run();
    }

    /**
     * Content stays: admins may have edited it since.
     */
    public function down(): void {}
};
