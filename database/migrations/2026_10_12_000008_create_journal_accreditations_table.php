<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_accreditations', function (Blueprint $table) {
            $table->id();
            $table->string('item_key', 80)->unique();
            $table->unsignedTinyInteger('rank');
            $table->string('journal_name');
            $table->text('publisher')->nullable();
            $table->string('e_issn_raw', 9)->nullable()->index();
            $table->boolean('e_issn_checksum_valid')->default(false);
            $table->string('decision_type', 40)->default('unknown');
            $table->unsignedTinyInteger('previous_rank')->nullable();
            $table->unsignedSmallInteger('valid_from_volume')->nullable();
            $table->unsignedTinyInteger('valid_from_issue')->nullable();
            $table->unsignedSmallInteger('valid_from_year')->nullable();
            $table->unsignedSmallInteger('valid_to_volume')->nullable();
            $table->unsignedTinyInteger('valid_to_issue')->nullable();
            $table->unsignedSmallInteger('valid_to_year')->nullable();
            $table->text('decision_text');
            $table->unsignedSmallInteger('source_page');
            $table->string('decree_number', 80);
            $table->date('decree_date');
            $table->string('period', 40);
            $table->string('source_pdf_sha256', 64);
            $table->foreignId('journal_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_status', 24)->index();
            $table->timestamps();
            $table->index(['rank', 'journal_name']);
            $table->index(['journal_source_id', 'journal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_accreditations');
    }
};
