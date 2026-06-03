<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One player's manual ordering of a tie the ranking engine could not resolve. `scope`
     * discriminates a within-group tie (`group_id` set) from the cross-group thirds cut
     * (`group_id` null). `tied_team_ids` is the exact tied set (sorted) the order resolves — the
     * engine ignores the row once the current tie no longer matches it — and `ordered_team_ids`
     * is the player's chosen order, a permutation of that set.
     */
    public function up(): void
    {
        Schema::create('entry_group_orderings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->json('tied_team_ids');
            $table->json('ordered_team_ids');
            $table->timestamps();

            // group_id is null for the thirds scope; MySQL/SQLite treat distinct NULLs as unique,
            // so one-thirds-row-per-entry is also enforced in app code via updateOrCreate.
            $table->unique(['entry_id', 'group_id', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entry_group_orderings');
    }
};
