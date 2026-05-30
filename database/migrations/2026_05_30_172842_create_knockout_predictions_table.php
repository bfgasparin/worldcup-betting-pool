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
        Schema::create('knockout_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('predicted_home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('predicted_away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();
            $table->foreignId('advancing_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->integer('points_awarded')->nullable();
            $table->timestamps();

            $table->unique(['entry_id', 'fixture_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knockout_predictions');
    }
};
