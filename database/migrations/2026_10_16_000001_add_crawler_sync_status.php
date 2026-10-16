<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sinta_page_checks', 'scopus_page_checks', 'm19_runs'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->boolean('synced')->default(false));
        }
    }

    public function down(): void
    {
        foreach (['sinta_page_checks', 'scopus_page_checks', 'm19_runs'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('synced'));
        }
    }
};
