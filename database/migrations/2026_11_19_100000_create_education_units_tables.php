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
        // Pondok education units (Program Pendidikan): Tahfidz, PDF Wustho, PDF Ulya, ...
        Schema::create('education_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->unsignedInteger('student_count')->nullable();
            $table->longText('description')->nullable();
            $table->foreignUuid('image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        // Curriculum rows of a unit, grouped on the page by class/level.
        Schema::create('education_unit_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('education_unit_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 60);
            $table->string('name');
            $table->string('books')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['education_unit_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('education_unit_subjects');
        Schema::dropIfExists('education_units');
    }
};
