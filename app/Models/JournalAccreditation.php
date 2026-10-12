<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalAccreditation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decree_date' => 'date', 'e_issn_checksum_valid' => 'boolean'];
    }

    public function source()
    {
        return $this->belongsTo(JournalSource::class, 'journal_source_id');
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
}
