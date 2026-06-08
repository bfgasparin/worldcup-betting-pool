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
        Schema::create('fixture_live_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('scheduled');
            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixture_live_states');
    }
};
