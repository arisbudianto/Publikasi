<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class M16Version extends Model
{
    protected $fillable = ['topic_id', 'number', 'title_cipher', 'summary_cipher', 'works', 'checked_at'];

    protected function casts(): array
    {
        return ['title_cipher' => 'encrypted', 'summary_cipher' => 'encrypted', 'works' => 'array', 'checked_at' => 'datetime'];
    }
}
