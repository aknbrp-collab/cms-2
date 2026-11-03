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
        // A corrected answer SIAKAD sends after publication waits here for a moderator.
        Schema::table('legal_questions', function (Blueprint $table) {
            $table->longText('pending_answer')->nullable()->after('answered_at');
            $table->string('pending_answered_by')->nullable()->after('pending_answer');
            $table->text('pending_answer_references')->nullable()->after('pending_answered_by');
            $table->timestamp('pending_answer_at')->nullable()->after('pending_answer_references');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_questions', function (Blueprint $table) {
            $table->dropColumn(['pending_answer', 'pending_answered_by', 'pending_answer_references', 'pending_answer_at']);
        });
    }
};
