<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_observations', function (Blueprint $table) {
            $table->string('metric_scheme', 80)->nullable();
            $table->unsignedSmallInteger('rank_value')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('fee_route', 40)->nullable();
            $table->boolean('waiver_available')->nullable();
            $table->string('language', 100)->nullable();
            $table->unsignedSmallInteger('frequency_per_year')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->index(['kind', 'metric_scheme', 'rank_value']);
            $table->index(['kind', 'fee_route', 'amount_minor']);
        });

        Schema::create('journal_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->char('value', 9)->unique();
            $table->string('source_url', 1000)->nullable();
            $table->date('checked_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->index(['journal_id', 'type']);
        });

        Schema::create('journal_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->string('model', 120);
            $table->char('scope_hash', 64);
            $table->unsignedSmallInteger('dimensions');
            $table->binary('vector');
            $table->timestamps();
            $table->unique(['journal_id', 'model']);
            $table->index(['model', 'scope_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_embeddings');
        Schema::dropIfExists('journal_identifiers');
        Schema::table('journal_observations', function (Blueprint $table) {
            $table->dropIndex(['kind', 'fee_route', 'amount_minor']);
            $table->dropIndex(['kind', 'metric_scheme', 'rank_value']);
            $table->dropColumn([
                'metric_scheme', 'rank_value', 'amount_minor', 'currency', 'fee_route',
                'waiver_available', 'language', 'frequency_per_year', 'effective_from', 'effective_to',
            ]);
        });
    }
};
