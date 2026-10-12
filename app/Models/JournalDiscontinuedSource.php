<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDiscontinuedSource extends Model
{
    protected $fillable = [
        'source_title', 'issn', 'discontinued_at', 'coverage_start_year', 'coverage_end_year', 'reason',
        'source_url', 'source_as_of', 'imported_at', 'is_current',
    ];

    protected function casts(): array
    {
        return ['discontinued_at' => 'date', 'source_as_of' => 'date', 'imported_at' => 'datetime', 'is_current' => 'boolean'];
    }
}
