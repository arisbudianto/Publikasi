<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('reference', 150)->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('due_at')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('submission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->date('occurred_at');
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::create('reviewer_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->string('reviewer', 100);
            $table->text('comment');
            $table->text('response')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['submission_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_responses');
        Schema::dropIfExists('submission_events');
        Schema::dropIfExists('submissions');
    }
};
