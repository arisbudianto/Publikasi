<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_sources', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('source_id')->unique();
            $t->string('name')->nullable();
            $t->string('publisher', 500)->nullable();
            $t->string('subject')->nullable();
            $t->string('claimed_rank', 30)->nullable();
            $t->string('p_issn', 9)->nullable()->index();
            $t->string('e_issn', 9)->nullable()->index();
            $t->string('official_url', 1000)->nullable();
            $t->string('source_url', 1000);
            $t->date('captured_at');
            $t->boolean('education')->index();
            $t->json('raw_identifiers');
            $t->json('article_titles');
            $t->string('dataset_sha256', 64);
            $t->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_sources');
    }
};
