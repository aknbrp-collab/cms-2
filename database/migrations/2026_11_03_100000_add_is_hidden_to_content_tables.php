<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose rows can be hidden from the public site without
     * changing their workflow status.
     *
     * @var list<string>
     */
    private const TABLES = ['posts', 'pages', 'events', 'galleries', 'publications', 'categories', 'menu_items'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('is_hidden')->default(false)->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('is_hidden');
            });
        }
    }
};
