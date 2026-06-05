<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Realign persisted pools to the renamed scoring strategy value. The column is a plain
     * string (no DB enum), so existing rows still hold the old 'world-cup-standard' value and
     * would fail to cast to the ScoringStrategy enum until updated. Uses the query builder to
     * bypass the model cast. No-op when no legacy rows exist (e.g. a fresh test database).
     */
    public function up(): void
    {
        DB::table('pools')
            ->where('scoring_strategy', 'world-cup-standard')
            ->update(['scoring_strategy' => 'upfront-bracket']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('pools')
            ->where('scoring_strategy', 'upfront-bracket')
            ->update(['scoring_strategy' => 'world-cup-standard']);
    }
};
