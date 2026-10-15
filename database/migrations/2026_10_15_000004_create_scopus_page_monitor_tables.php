<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scopus_page_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scopus_source_id')->constrained('scopus_sources')->cascadeOnDelete();
            $table->string('url', 1000);
            $table->string('field', 20);
            $table->boolean('enabled')->default(false);
            $table->timestamp('next_check_at')->nullable()->index();
            $table->char('last_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['scopus_source_id', 'url', 'field'], 'scopus_target_unique');
        });
        Schema::create('scopus_page_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained('scopus_page_targets')->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->timestamp('created_at');
            $table->index(['target_id', 'created_at']);
        });
        Schema::create('scopus_page_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained('scopus_page_targets')->cascadeOnDelete();
            $table->foreignId('check_id')->constrained('scopus_page_checks')->cascadeOnDelete();
            $table->text('quote');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scopus_page_candidates');
        Schema::dropIfExists('scopus_page_checks');
        Schema::dropIfExists('scopus_page_targets');
    }
};
