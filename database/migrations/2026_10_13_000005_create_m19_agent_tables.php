<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m19_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->string('url', 1000);
            $table->string('field', 20);
            $table->boolean('enabled')->default(false);
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['enabled', 'next_check_at']);
        });
        Schema::create('m19_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('m19_sources')->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('error_code', 60)->nullable();
            $table->timestamp('created_at');
            $table->index(['source_id', 'created_at']);
        });
        Schema::create('m19_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('m19_sources')->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('m19_runs')->cascadeOnDelete();
            $table->string('field', 20);
            $table->string('url', 1000);
            $table->text('quote');
            $table->string('content_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m19_candidates');
        Schema::dropIfExists('m19_runs');
        Schema::dropIfExists('m19_sources');
    }
};
