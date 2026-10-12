<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalObservation extends Model
{
    protected $fillable = ['kind', 'label', 'value', 'verification', 'source_url', 'checked_at', 'period', 'reviewed_by', 'review_note', 'retired_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'date', 'retired_at' => 'datetime'];
    }
}
