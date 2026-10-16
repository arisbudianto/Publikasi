<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['journal_sources', 'scopus_sources'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->text('scope_excerpt')->nullable();
                $t->string('scope_source_url', 1000)->nullable();
                $t->date('scope_checked_at')->nullable();
            });
        }
        Schema::table('scopus_page_checks', fn (Blueprint $t) => $t->string('evidence_url', 1000)->nullable());
    }

    public function down(): void
    {
        Schema::table('scopus_page_checks', fn (Blueprint $t) => $t->dropColumn('evidence_url'));
        foreach (['journal_sources', 'scopus_sources'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['scope_excerpt', 'scope_source_url', 'scope_checked_at']));
        }
    }
};
