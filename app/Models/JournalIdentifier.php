<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalIdentifier extends Model
{
    protected $fillable = ['type', 'value', 'source_url', 'checked_at', 'retired_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'date', 'retired_at' => 'datetime'];
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
}
