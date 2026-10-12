<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_shortlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'journal_id']);
        });
        Schema::create('match_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 300);
            $t->string('mode', 20);
            $t->json('candidates');
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_histories');
        Schema::dropIfExists('personal_shortlists');
    }
};
