<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scopus_page_targets', function (Blueprint $table) {
            $table->boolean('auto_verify')->default(false);
            $table->string('verification_error', 60)->nullable();
            $table->timestamp('verification_checked_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('scopus_page_targets', function (Blueprint $table) {
            $table->dropColumn(['auto_verify', 'verification_error', 'verification_checked_at']);
        });
    }
};
