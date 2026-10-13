<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class M16Topic extends Model
{
    protected $fillable = ['user_id', 'current_version', 'last_checked_at'];

    protected function casts(): array
    {
        return ['last_checked_at' => 'datetime'];
    }

    public function versions()
    {
        return $this->hasMany(M16Version::class, 'topic_id');
    }

    public function findings()
    {
        return $this->hasMany(M16Finding::class, 'topic_id');
    }
}
