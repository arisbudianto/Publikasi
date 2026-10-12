<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalObservation extends Model
{
    protected $fillable = [
        'kind', 'label', 'value', 'verification', 'source_url', 'checked_at', 'period', 'reviewed_by', 'review_note', 'retired_at',
        'metric_scheme', 'rank_value', 'amount_minor', 'currency', 'fee_route', 'waiver_available', 'language',
        'frequency_per_year', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'date', 'retired_at' => 'datetime', 'waiver_available' => 'boolean',
            'effective_from' => 'date', 'effective_to' => 'date',
        ];
    }
}
