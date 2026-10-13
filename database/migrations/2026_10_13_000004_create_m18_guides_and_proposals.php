<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m18_guides', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title', 180);
            $table->longText('body');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->date('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('m18_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->string('journal_name', 255);
            $table->string('field', 30);
            $table->string('proposed_value', 1000);
            $table->string('source_url', 1000);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->foreignId('applied_observation_id')->nullable()->constrained('journal_observations')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('m18_proposal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('m18_proposals')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m18_proposal_events');
        Schema::dropIfExists('m18_proposals');
        Schema::dropIfExists('m18_guides');
    }
};
