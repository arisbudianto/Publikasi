<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $fillable = ['name', 'publisher', 'issn', 'official_url', 'scope_summary', 'subject', 'scope_source_url', 'checked_at', 'status', 'reuse_status'];

    protected function casts(): array
    {
        return ['checked_at' => 'date'];
    }

    public function observations()
    {
        return $this->hasMany(JournalObservation::class)->whereNull('retired_at');
    }

    public function allObservations()
    {
        return $this->hasMany(JournalObservation::class);
    }

    public function accreditations()
    {
        return $this->hasMany(JournalAccreditation::class);
    }

    public function needsReview(): bool
    {
        return $this->checked_at->lt(today()->subDays(180));
    }
}
