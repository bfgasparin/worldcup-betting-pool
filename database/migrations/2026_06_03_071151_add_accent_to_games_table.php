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
        Schema::table('games', function (Blueprint $table) {
            // The game's colour identity (see App\Enums\GameAccent). Nullable so games without an
            // explicit accent fall back to a positional colour in the UI.
            $table->string('accent')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('accent');
        });
    }
};
