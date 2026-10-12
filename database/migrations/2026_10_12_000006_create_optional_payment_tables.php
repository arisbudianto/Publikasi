<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_idr');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('support_payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('note', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_payment_events');
        Schema::dropIfExists('support_payments');
    }
};
