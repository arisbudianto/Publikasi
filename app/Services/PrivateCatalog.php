<?php

namespace App\Services;

use App\Models\MatchHistory;
use App\Models\User;

class PrivateCatalog
{
    public function record(User $user, string $title, string $mode, array $results): void
    {
        $candidates = array_map(fn ($r) => ['id' => $r['journal']->id, 'name' => $r['journal']->name, 'scope_source_url' => $r['journal']->scope_source_url, 'checked_at' => $r['journal']->checked_at->format('Y-m-d')], $results);
        MatchHistory::create(['user_id' => $user->id, 'title' => $title, 'mode' => $mode, 'candidates' => $candidates]);
    }
}
