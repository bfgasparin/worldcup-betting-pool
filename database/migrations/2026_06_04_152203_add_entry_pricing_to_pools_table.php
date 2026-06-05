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
        Schema::table('pools', function (Blueprint $table) {
            // What a player pays the organizer to join the pool. Payment is handled externally;
            // this is only the displayed buy-in, in the pool's currency.
            $table->decimal('entry_price', 10, 2)->default(0)->after('predictions_lock_at');
            // ISO 4217 code for the buy-in and the computed prizes (e.g. BRL).
            $table->string('currency', 3)->default('BRL')->after('entry_price');
            // The cut the organizer takes off the top before prizes are split (percentage, e.g. 15.00 = 15%).
            $table->decimal('house_fee_percentage', 5, 2)->default(0)->after('currency');
            // Per-place split of the net pot: list of {place:int, percentage:float}; percentages sum to 100.
            $table->json('prize_structure')->nullable()->after('house_fee_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropColumn(['entry_price', 'currency', 'house_fee_percentage', 'prize_structure']);
        });
    }
};
