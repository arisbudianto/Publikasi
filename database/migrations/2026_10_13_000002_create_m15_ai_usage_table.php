<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m15_ai_usage', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7)->unique();
            $table->decimal('spent_usd', 10, 6)->default(0);
            $table->decimal('reserved_usd', 10, 6)->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('analysis_count')->default(0);
            $table->unsignedInteger('openalex_requests')->default(0);
            $table->decimal('openalex_cost_usd', 10, 6)->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m15_ai_usage');
    }
};
