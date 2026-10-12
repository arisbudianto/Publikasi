<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalObservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ObservationReview
{
    public function publish(Journal $journal, array $data, int $actor): JournalObservation
    {
        return DB::transaction(function () use ($journal, $data, $actor) {
            Journal::whereKey($journal->id)->lockForUpdate()->firstOrFail();
            $old = $journal->allObservations()->whereNull('retired_at')->where('kind', $data['kind'])->whereRaw('LOWER(label) = ?', [Str::lower($data['label'])])->get();
            foreach ($old as $item) {
                $item->update(['retired_at' => now()]);
            }
            $new = $journal->allObservations()->create($data + ['reviewed_by' => $actor]);
            DB::table('catalog_audits')->insert(['user_id' => $actor, 'journal_id' => $journal->id, 'action' => 'observation_published', 'snapshot' => json_encode(['replaced' => $old->toArray(), 'after' => $new->toArray()]), 'created_at' => now()]);

            return $new;
        });
    }

    public function retire(Journal $journal, int $id, int $actor, string $reason): void
    {
        DB::transaction(function () use ($journal, $id, $actor, $reason) {
            Journal::whereKey($journal->id)->lockForUpdate()->firstOrFail();
            $item = $journal->allObservations()->whereNull('retired_at')->findOrFail($id);
            $before = $item->toArray();
            $item->update(['retired_at' => now()]);
            DB::table('catalog_audits')->insert(['user_id' => $actor, 'journal_id' => $journal->id, 'action' => 'observation_retired', 'snapshot' => json_encode(['before' => $before, 'reason' => $reason]), 'created_at' => now()]);
        });
    }
}
