<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        Schema::create('journals', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('publisher');
            $t->string('issn', 9)->unique();
            $t->string('official_url', 1000);
            $t->text('scope_summary');
            $t->string('subject');
            $t->string('scope_source_url', 1000);
            $t->date('checked_at');
            $t->string('status', 20)->default('draft')->index();
            $t->string('reuse_status')->default('manual-summary');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('journal_observations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20);
            $t->string('label', 120);
            $t->string('value', 255);
            $t->string('verification', 20)->default('publisher_claim');
            $t->string('source_url', 1000);
            $t->date('checked_at');
            $t->string('period', 120)->nullable();
            $t->timestamps();
        });
        Schema::create('catalog_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $t->string('action');
            $t->json('snapshot');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_audits');
        Schema::dropIfExists('journal_observations');
        Schema::dropIfExists('journals');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
