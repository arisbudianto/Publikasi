<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionChecklist extends Model
{
    protected $fillable = ['user_id', 'journal_id', 'answers', 'note', 'version'];

    protected function casts(): array
    {
        return ['answers' => 'array'];
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
}
