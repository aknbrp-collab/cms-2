<?php

use Database\Seeders\KolomAlumniSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Add the Pondok "Kolom Alumni" category and its link under "Berita" to
     * an existing install (v3). Does nothing on a fresh database without
     * sites; the demo seeder adds them there.
     */
    public function up(): void
    {
        (new KolomAlumniSeeder)->run();
    }

    /**
     * The category stays: posts may have been filed under it since.
     */
    public function down(): void {}
};
