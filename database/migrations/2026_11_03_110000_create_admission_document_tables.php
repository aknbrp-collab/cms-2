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
        // Documents an admission period asks for, e.g. "Kartu Keluarga".
        Schema::create('admission_requirements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admission_period_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('file_types');
            $table->unsignedInteger('max_size_kb')->default(5120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One uploaded file per registration and requirement.
        Schema::create('admission_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admission_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('admission_requirement_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('note', 1000)->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['admission_registration_id', 'admission_requirement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_documents');
        Schema::dropIfExists('admission_requirements');
    }
};
