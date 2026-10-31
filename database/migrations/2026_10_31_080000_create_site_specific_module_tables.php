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
        // Pondok Pesantren
        Schema::create('facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->siteColumns($table);
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $this->listingColumns($table);
        });

        Schema::create('extracurriculars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->siteColumns($table);
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('schedule')->nullable();
            $table->string('coach')->nullable();
            $table->foreignUuid('image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $this->listingColumns($table);
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->siteColumns($table);
            $table->string('title');
            $table->string('student_name');
            $table->string('level')->index();
            $table->string('event_name')->nullable();
            $table->date('achieved_on')->index();
            $table->text('description')->nullable();
            $table->foreignUuid('image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $this->listingColumns($table);
        });

        // Ma'had Aly
        Schema::create('lecturers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->siteColumns($table);
            $table->string('name');
            $table->string('slug');
            $table->string('nidn', 20)->nullable();
            $table->string('position')->nullable();
            $table->string('expertise')->nullable();
            $table->text('education')->nullable();
            $table->string('email')->nullable();
            $table->foreignUuid('photo_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('bio')->nullable();
            $table->string('external_id')->nullable()->index();
            $this->listingColumns($table);

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->siteColumns($table);
            $table->string('name');
            $table->string('slug');
            $table->string('level');
            $table->string('degree')->nullable();
            $table->string('accreditation')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->text('core_books')->nullable();
            $table->foreignUuid('image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $this->listingColumns($table);

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->unsignedTinyInteger('credits')->default(2);
            $table->string('books')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->siteColumns($table);
            $table->string('name');
            $table->string('slug');
            $table->string('issn', 20)->nullable();
            $table->string('e_issn', 20)->nullable();
            $table->string('accreditation')->nullable();
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->foreignUuid('cover_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $this->listingColumns($table);

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->siteColumns($table);
            $table->string('name');
            $table->string('slug');
            $table->string('type')->index();
            $table->string('chairperson')->nullable();
            $table->string('period')->nullable();
            $table->text('description')->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->foreignUuid('logo_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $this->listingColumns($table);

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->siteColumns($table);
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('title');
            $table->string('slug');
            $table->string('authors');
            $table->text('abstract')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedSmallInteger('year')->index();
            $table->foreignUuid('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('volume', 20)->nullable();
            $table->string('issue', 20)->nullable();
            $table->string('pages', 20)->nullable();
            $table->string('doi')->nullable();
            $table->string('keywords')->nullable();
            $table->foreignUuid('file_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('external_url', 2048)->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('external_source')->nullable();
            $table->string('external_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index(['external_source', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publications');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('lecturers');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('extracurriculars');
        Schema::dropIfExists('facilities');
    }

    protected function siteColumns(Blueprint $table): void
    {
        $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
    }

    /**
     * Ordering, publish switch and timestamps of reference content.
     */
    protected function listingColumns(Blueprint $table): void
    {
        $table->unsignedInteger('sort_order')->default(0);
        $table->boolean('is_published')->default(false)->index();
        $table->timestamps();
    }
};
