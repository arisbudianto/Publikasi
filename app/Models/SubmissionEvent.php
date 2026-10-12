<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionEvent extends Model
{
    protected $fillable = ['submission_id', 'from_status', 'to_status', 'occurred_at', 'note'];

    protected function casts(): array
    {
        return ['occurred_at' => 'date'];
    }
}
