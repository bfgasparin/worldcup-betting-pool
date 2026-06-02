<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give the FF&A game its own slug so it no longer collides with the generic tournament slug
     * 'world-cup-2026' (a second game — Brothers Association — is now seeded over the same
     * tournament, and game routes bind {game:slug}). Renames only the games row; the tournament
     * keeps 'world-cup-2026'. Uses the query builder so it runs regardless of model state, and is
     * a no-op when no legacy row exists (e.g. a fresh database the seeder builds directly).
     */
    public function up(): void
    {
        DB::table('games')
            ->where('slug', 'world-cup-2026')
            ->update(['slug' => 'world-cup-2026-ffa']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('games')
            ->where('slug', 'world-cup-2026-ffa')
            ->update(['slug' => 'world-cup-2026']);
    }
};
