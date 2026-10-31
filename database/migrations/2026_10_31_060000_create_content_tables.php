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
        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('show_on_home')->default(false);
            $table->unsignedInteger('home_order')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('news')->index();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignUuid('featured_image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_featured')->default(false);
            $this->workflowColumns($table);
            $table->string('external_source')->nullable();
            $table->string('external_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index(['external_source', 'external_id']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable()->index();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('template')->default('default');
            $table->json('blocks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $this->workflowColumns($table);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        // Self-references are added once the primary key exists.
        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('pages')->nullOnDelete();
        });

        Schema::create('seo_meta', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('seoable');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->foreignUuid('og_image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('canonical_url', 2048)->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('location');
            $table->string('name');
            $table->timestamps();

            $table->unique(['site_id', 'location']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('menu_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('label');
            $table->string('type')->default('url');
            $table->string('url', 2048)->nullable();
            $table->nullableUuidMorphs('linkable');
            $table->boolean('open_in_new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('menu_items')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('media_assets');
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
