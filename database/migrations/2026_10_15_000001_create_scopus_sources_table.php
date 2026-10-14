<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scopus_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_record_id');
            $table->string('source_title', 500);
            $table->string('issn', 8)->nullable()->index();
            $table->string('eissn', 8)->nullable()->index();
            $table->string('status', 16);
            $table->string('coverage', 500)->nullable();
            $table->boolean('discontinued')->default(false);
            $table->string('source_type', 32);
            $table->string('publisher', 500)->nullable();
            $table->string('asjc_codes', 500)->nullable();
            $table->char('snapshot_month', 7);
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->unique(['source_record_id', 'snapshot_month']);
            $table->index(['is_current', 'source_type', 'source_title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scopus_sources');
    }
};
