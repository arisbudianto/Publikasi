<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalIntegrityAssessment extends Model
{
    protected $fillable = ['user_id', 'journal_id', 'criteria_version', 'answers', 'risk_score', 'unknown_count', 'outcome'];

    protected function casts(): array
    {
        return ['answers' => 'array'];
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
