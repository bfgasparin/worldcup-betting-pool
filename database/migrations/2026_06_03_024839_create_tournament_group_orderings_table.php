<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An admin's manual ordering of a tie in the OFFICIAL group results that the ranking engine
     * could not resolve — the tournament-wide mirror of `entry_group_orderings`. `scope`
     * discriminates a within-group tie (`group_id` set) from the cross-group thirds cut
     * (`group_id` null); `tied_team_ids` is the exact tied set the order resolves and
     * `ordered_team_ids` the admin's chosen order.
     */
    public function up(): void
    {
        Schema::create('tournament_group_orderings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->json('tied_team_ids');
            $table->json('ordered_team_ids');
            $table->timestamps();

            $table->unique(['tournament_id', 'group_id', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_group_orderings');
    }
};
