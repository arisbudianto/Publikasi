<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class M18Proposal extends Model
{
    protected $fillable = ['user_id', 'journal_id', 'journal_name', 'field', 'proposed_value', 'source_url', 'note',
        'status', 'reviewed_by', 'decision_note', 'applied_observation_id'];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function events()
    {
        return $this->hasMany(M18ProposalEvent::class, 'proposal_id');
    }
}
