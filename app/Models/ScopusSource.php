<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScopusSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['discontinued' => 'boolean', 'is_current' => 'boolean'];
    }
}
