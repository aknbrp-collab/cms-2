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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('external_id');
            $table->string('provider_user_id')->nullable()->index();
            $table->string('name');
            $table->string('handle')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('meta')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('last_error')->nullable();
            $table->foreignUuid('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'provider', 'external_id']);
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('caption');
            $table->json('media')->nullable();
            $table->string('link', 2048)->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('social_post_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->constrained()->cascadeOnDelete();
            $table->text('caption_override')->nullable();
            $table->string('hashtags', 1000)->nullable();
            $table->json('options')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('external_post_id')->nullable();
            $table->string('permalink', 2048)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'social_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_post_targets');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};
