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
        Schema::table('users', function (Blueprint $table) {
            // The phone was only ever a placeholder "import-matching identity" that was never used;
            // pre-registered players are now identified by name (and id) alone. Drop the unique index
            // before the column so SQLite/MySQL both release it cleanly.
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
        });
    }
};
