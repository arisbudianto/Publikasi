<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class M18ProposalEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'from_status', 'to_status', 'note', 'created_at'];
}
