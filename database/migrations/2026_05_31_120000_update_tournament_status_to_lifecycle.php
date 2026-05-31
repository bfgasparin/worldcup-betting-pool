<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Collapse the retired prediction-flavoured statuses into the new lifecycle's
        // starting state; in_progress and completed carry over unchanged.
        DB::table('tournaments')
            ->whereIn('status', ['draft', 'open', 'locked'])
            ->update(['status' => 'upcoming']);

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('status')->default('upcoming')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }
};
