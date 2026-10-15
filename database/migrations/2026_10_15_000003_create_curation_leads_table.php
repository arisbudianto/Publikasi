<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curation_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_source_id')->unique()->constrained('journal_sources')->cascadeOnDelete();
            $table->foreignId('accreditation_id')->constrained('journal_accreditations')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curation_leads');
    }
};
