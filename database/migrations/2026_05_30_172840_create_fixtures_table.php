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
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('match_number');
            $table->string('bracket_slot')->nullable();

            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('home_placeholder_label')->nullable();
            $table->string('away_placeholder_label')->nullable();

            $table->foreignId('home_feeder_fixture_id')->nullable()->constrained('fixtures')->nullOnDelete();
            $table->foreignId('away_feeder_fixture_id')->nullable()->constrained('fixtures')->nullOnDelete();
            $table->string('home_feeder_outcome')->nullable();
            $table->string('away_feeder_outcome')->nullable();

            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();

            $table->dateTime('kicks_off_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();

            $table->unique(['tournament_id', 'match_number']);
            $table->index(['tournament_id', 'bracket_slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
