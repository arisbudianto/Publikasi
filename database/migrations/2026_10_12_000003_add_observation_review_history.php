<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_observations', function (Blueprint $t) {
            $t->timestamp('retired_at')->nullable()->index();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('review_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('journal_observations', function (Blueprint $t) {
            $t->dropForeign(['reviewed_by']);
            $t->dropColumn(['reviewed_by', 'review_note', 'retired_at']);
        });
    }
};
