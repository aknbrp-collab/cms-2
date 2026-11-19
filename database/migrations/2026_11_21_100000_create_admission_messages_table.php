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
        // WhatsApp messages to admission guardians: opened from the admin
        // ("link") or sent by a gateway ("gateway").
        Schema::create('admission_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admission_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30);
            $table->string('channel', 20)->default('link');
            $table->string('status', 20)->default('opened');
            $table->string('phone', 20);
            $table->text('body');
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['admission_registration_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_messages');
    }
};
