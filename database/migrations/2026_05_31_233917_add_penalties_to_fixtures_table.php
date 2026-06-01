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
        Schema::table('fixtures', function (Blueprint $table) {
            $table->unsignedTinyInteger('home_penalties')->nullable()->after('away_goals');
            $table->unsignedTinyInteger('away_penalties')->nullable()->after('home_penalties');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropColumn(['home_penalties', 'away_penalties']);
        });
    }
};
