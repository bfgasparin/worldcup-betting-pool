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
            // Relative path on the `public` disk (e.g. avatars/42/ab12.jpg); the model exposes a URL accessor.
            $table->string('avatar_path')->nullable()->after('phone');

            // Null until the first-login onboarding wizard is finished or skipped through.
            $table->timestamp('onboarded_at')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'onboarded_at']);
        });
    }
};
