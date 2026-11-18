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
        // SIAKAD contract 1.2: an article may carry a picture. The CMS keeps its
        // own copy in the media library because SIAKAD's link stops working.
        Schema::table('siakad_articles', function (Blueprint $table) {
            $table->string('image_url', 2048)->nullable();
            $table->string('image_caption')->nullable();
            $table->foreignUuid('image_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('image_source_url', 2048)->nullable();
            $table->string('image_error', 500)->nullable();
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->foreignUuid('image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('image_caption')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_id');
            $table->dropColumn('image_caption');
        });

        Schema::table('siakad_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_asset_id');
            $table->dropColumn(['image_url', 'image_caption', 'image_source_url', 'image_error']);
        });
    }
};
