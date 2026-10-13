<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class M18Guide extends Model
{
    protected $fillable = ['slug', 'title', 'body', 'author_id', 'published_at', 'reviewed_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'reviewed_at' => 'date'];
    }
}
