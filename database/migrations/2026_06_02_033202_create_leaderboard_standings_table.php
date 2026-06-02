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
        Schema::create('leaderboard_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->string('category')->index();
            $table->integer('value')->default(0);
            $table->integer('tiebreaker')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedInteger('previous_rank')->nullable();
            $table->timestamps();

            $table->unique(['entry_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboard_standings');
    }
};
