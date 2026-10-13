<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class M15AiUsage extends Model
{
    protected $table = 'm15_ai_usage';

    protected $fillable = ['period', 'spent_usd', 'reserved_usd', 'request_count', 'analysis_count', 'openalex_requests', 'openalex_cost_usd', 'input_tokens', 'output_tokens'];

    protected function casts(): array
    {
        return [
            'spent_usd' => 'decimal:6',
            'reserved_usd' => 'decimal:6',
            'request_count' => 'integer',
            'analysis_count' => 'integer',
            'openalex_requests' => 'integer',
            'openalex_cost_usd' => 'decimal:6',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }
}
