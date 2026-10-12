<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_discontinued_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_title', 500);
            $table->char('issn', 9);
            $table->date('discontinued_at')->nullable();
            $table->unsignedSmallInteger('coverage_start_year')->nullable();
            $table->unsignedSmallInteger('coverage_end_year')->nullable();
            $table->text('reason')->nullable();
            $table->string('source_url', 1000);
            $table->date('source_as_of');
            $table->timestamp('imported_at');
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->unique(['issn', 'source_as_of']);
            $table->index(['is_current', 'source_title']);
        });

        Schema::create('journal_integrity_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('criteria_version', 32);
            $table->json('answers');
            $table->unsignedSmallInteger('risk_score');
            $table->unsignedTinyInteger('unknown_count');
            $table->string('outcome', 80);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_integrity_assessments');
        Schema::dropIfExists('journal_discontinued_sources');
    }
};
