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
        Schema::table('users', function (Blueprint $table) {
            // Pre-registered users come from a name+phone list and are matched to imported
            // predictions by phone. Nullable so email-first users have none; unique so a phone
            // maps to at most one account.
            $table->string('phone')->nullable()->unique()->after('email');

            // Pre-registered users have no email yet (set later via the user:set-email command).
            // The separate users_email_unique index is left untouched by change(), so many NULL
            // emails coexist while real emails stay unique.
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
            $table->string('email')->nullable(false)->change();
        });
    }
};
