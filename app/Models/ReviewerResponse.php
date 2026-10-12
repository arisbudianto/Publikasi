<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewerResponse extends Model
{
    protected $fillable = ['submission_id', 'round', 'reviewer', 'comment', 'response', 'location', 'status', 'version'];

    protected function casts(): array
    {
        return ['round' => 'integer', 'version' => 'integer'];
    }
}
