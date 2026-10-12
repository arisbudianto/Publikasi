<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalShortlist extends Model
{
    protected $fillable = ['user_id', 'journal_id'];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
}
