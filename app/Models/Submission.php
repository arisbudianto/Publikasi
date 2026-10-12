<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = ['user_id', 'journal_id', 'title', 'reference', 'status', 'due_at', 'note', 'version'];

    protected function casts(): array
    {
        return ['due_at' => 'date', 'version' => 'integer'];
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function events()
    {
        return $this->hasMany(SubmissionEvent::class);
    }

    public function responses()
    {
        return $this->hasMany(ReviewerResponse::class);
    }

    public const LABELS = ['draft' => 'Persiapan', 'submitted' => 'Diajukan', 'review' => 'Dalam review', 'revision' => 'Perlu revisi', 'accepted' => 'Diterima', 'rejected' => 'Ditolak', 'withdrawn' => 'Ditarik'];

    public const TRANSITIONS = ['draft' => ['submitted', 'withdrawn'], 'submitted' => ['review', 'revision', 'accepted', 'rejected', 'withdrawn'], 'review' => ['revision', 'accepted', 'rejected', 'withdrawn'], 'revision' => ['submitted', 'accepted', 'rejected', 'withdrawn'], 'accepted' => [], 'rejected' => [], 'withdrawn' => []];
}
