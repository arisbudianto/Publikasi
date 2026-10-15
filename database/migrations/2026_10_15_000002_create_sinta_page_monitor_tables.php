<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sinta_page_states', function (Blueprint $table) {
            $table->foreignId('journal_source_id')->primary()->constrained('journal_sources')->cascadeOnDelete();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->char('last_hash', 64)->nullable();
            $table->timestamps();
        });
        Schema::create('sinta_page_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_source_id')->constrained('journal_sources')->cascadeOnDelete();
            $table->string('url', 1000);
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->timestamp('created_at');
            $table->index(['journal_source_id', 'created_at']);
        });
        Schema::create('sinta_page_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_source_id')->constrained('journal_sources')->cascadeOnDelete();
            $table->foreignId('check_id')->constrained('sinta_page_checks')->cascadeOnDelete();
            $table->text('quote');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sinta_page_candidates');
        Schema::dropIfExists('sinta_page_checks');
        Schema::dropIfExists('sinta_page_states');
    }
};
