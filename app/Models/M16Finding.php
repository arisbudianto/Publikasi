<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class M16Finding extends Model
{
    protected $fillable = ['topic_id', 'work_id', 'work', 'seen_at', 'read_at'];

    protected function casts(): array
    {
        return ['work' => 'array', 'seen_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
