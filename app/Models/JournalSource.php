<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalSource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['education' => 'boolean', 'captured_at' => 'date', 'article_titles' => 'array', 'raw_identifiers' => 'array'];
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
}
