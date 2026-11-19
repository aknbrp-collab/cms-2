<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Small tag on a slide of the Pondok hero slider, e.g. "Prestasi Nasional".
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('label', 60)->nullable()->after('image_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
