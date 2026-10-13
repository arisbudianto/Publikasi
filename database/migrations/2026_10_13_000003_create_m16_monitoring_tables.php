<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m16_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'last_checked_at']);
        });
        Schema::create('m16_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('m16_topics')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->text('title_cipher');
            $table->text('summary_cipher');
            $table->json('works')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['topic_id', 'number']);
        });
        Schema::create('m16_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('m16_topics')->cascadeOnDelete();
            $table->string('work_id', 80);
            $table->json('work');
            $table->timestamp('seen_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['topic_id', 'work_id']);
        });
        Schema::create('m16_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 12)->default('request');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->timestamps();
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m16_runs');
        Schema::dropIfExists('m16_findings');
        Schema::dropIfExists('m16_versions');
        Schema::dropIfExists('m16_topics');
    }
};
