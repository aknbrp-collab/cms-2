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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('label')->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('location')->nullable();
            $table->string('audience')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->foreignUuid('featured_image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $this->workflowColumns($table);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('galleries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('photo')->index();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->date('taken_on')->nullable();
            $table->foreignUuid('cover_image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $this->workflowColumns($table);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('media_asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('video_url', 2048)->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('group')->nullable()->index();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('leaders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position');
            $table->string('expertise')->nullable();
            $table->foreignUuid('photo_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('bio')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('admission_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('academic_year');
            $table->date('opens_on');
            $table->date('closes_on');
            $table->text('summary')->nullable();
            $table->longText('requirements')->nullable();
            $table->longText('fees')->nullable();
            $table->json('steps')->nullable();
            $table->json('programs')->nullable();
            $table->foreignUuid('brochure_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('accepts_online_registration')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('admission_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('admission_period_id')->constrained()->cascadeOnDelete();
            $table->string('registration_number')->unique();
            $table->string('status')->default('new')->index();
            $table->string('full_name');
            $table->string('gender', 1);
            $table->string('birth_place');
            $table->date('birth_date');
            $table->string('nisn', 20)->nullable();
            $table->string('previous_school');
            $table->string('program')->nullable();
            $table->string('parent_name');
            $table->string('parent_phone', 30);
            $table->string('email')->nullable();
            $table->text('address');
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_registrations');
        Schema::dropIfExists('admission_periods');
        Schema::dropIfExists('leaders');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('events');
    }

    /**
     * Columns shared by every model that goes through the editorial workflow.
     */
    protected function workflowColumns(Blueprint $table): void
    {
        $table->string('status')->default('draft')->index();
        $table->timestamp('submitted_at')->nullable();
        $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('review_note')->nullable();
        $table->timestamp('publish_at')->nullable()->index();
        $table->timestamp('published_at')->nullable()->index();
    }
};
