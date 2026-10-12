<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_checklists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $t->json('answers');
            $t->text('note')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['user_id', 'journal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_checklists');
    }
};
