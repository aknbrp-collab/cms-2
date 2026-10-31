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
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('system')->default('siakad')->index();
            $table->string('direction');
            $table->string('action')->index();
            $table->string('status')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->nullableUuidMorphs('subject');
            $table->string('external_id')->nullable()->index();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('siakad_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('type');
            $table->string('title');
            $table->string('authors');
            $table->text('abstract')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('keywords')->nullable();
            $table->string('doi')->nullable();
            $table->string('file_url', 2048)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->json('payload');
            $table->timestamp('received_at');
            $table->boolean('updated_after_approval')->default(false);
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignUuid('publication_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
        });

        Schema::create('legal_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_code')->unique();
            $table->string('status')->default('submitted')->index();
            $table->string('category');
            $table->string('asker_name')->nullable();
            $table->string('asker_email')->nullable();
            $table->string('asker_city')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->text('question');
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->text('public_question')->nullable();
            $table->text('moderation_note')->nullable();
            $table->foreignUuid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_id')->nullable()->unique();
            $table->timestamp('forwarded_at')->nullable();
            $table->longText('answer')->nullable();
            $table->string('answered_by')->nullable();
            $table->text('answer_references')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_questions');
        Schema::dropIfExists('siakad_articles');
        Schema::dropIfExists('integration_logs');
    }
};
