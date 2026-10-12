<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchHistory extends Model
{
    protected $fillable = ['user_id', 'title', 'mode', 'candidates'];

    protected function casts(): array
    {
        return ['candidates' => 'array'];
    }
}
