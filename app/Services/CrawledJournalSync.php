<?php

namespace App\Services;

use App\Models\Journal;
use App\Rules\ValidIssn;
use Illuminate\Support\Facades\DB;

class CrawledJournalSync
{
    public function __construct(private JournalPageFetcher $fetcher, private Catalog $catalog) {}

    public function fromSinta(int $sourceId, string $url, string $quote): bool
    {
        $source = DB::table('journal_sources')->where('id', $sourceId)->first();
        if (! $source || ! filled($source->name) || ! filled($source->publisher)
            || ! $this->sameSite($source->official_url, $url)) return false;

        $accreditation = DB::table('journal_accreditations')
            ->where('journal_source_id', $sourceId)->where('match_status', 'matched')
            ->where('e_issn_checksum_valid', true)->whereBetween('rank', [1, 4])
            ->orderByDesc('decree_date')->first();
        if (! $accreditation) return false;
        $issn = $this->issn($accreditation->e_issn_raw);
        if (! $issn || ! in_array($issn, [$this->issn($source->e_issn), $this->issn($source->p_issn)], true)) return false;

        return DB::transaction(function () use ($source, $accreditation, $issn, $url, $quote) {
            $journalIds = array_values(array_unique(array_filter([
                $this->issn($source->p_issn), $this->issn($source->e_issn),
            ])));
            $matches = Journal::whereIn('issn', $journalIds)->lockForUpdate()->get();
            if ($matches->count() > 1) return false;
            $journal = $matches->first();
            if ($journal && ($journal->status === 'archived' || ! $this->sameSite($journal->official_url, $url))) return false;
            if ($source->journal_id && $journal && (int) $source->journal_id !== $journal->id) return false;
            if ($source->journal_id && ! $journal) return false;
            if ($accreditation->journal_id && (! $journal || (int) $accreditation->journal_id !== $journal->id)) return false;
            if (! $journal && DB::table('journal_identifiers')->where('value', $issn)->exists()) return false;
            $data = [
                'scope_summary' => $quote, 'scope_source_url' => $url,
                'checked_at' => now()->toDateString(), 'reuse_status' => 'crawler-source',
            ];
            if (! $journal) {
                $data += ['name' => $source->name, 'publisher' => mb_substr($source->publisher, 0, 255),
                    'issn' => $issn, 'official_url' => preg_replace('/^http:/i', 'https:', $source->official_url),
                    'subject' => mb_substr($source->subject ?: 'Belum diklasifikasi', 0, 255), 'status' => 'published'];
            } elseif ($journal->scope_summary === $quote && $journal->scope_source_url === $url) {
                return false;
            }
            $journal = $this->catalog->save($data, $journal);
            DB::table('journal_sources')->where('id', $source->id)->update(['journal_id' => $journal->id]);
            DB::table('journal_accreditations')->where('id', $accreditation->id)->update(['journal_id' => $journal->id]);
            return true;
        });
    }

    public function fromScopus(int $targetId, string $quote): bool
    {
        $target = DB::table('scopus_page_targets as t')->join('scopus_sources as s', 's.id', '=', 't.scopus_source_id')
            ->where('t.id', $targetId)->where('t.enabled', true)
            ->where('s.is_current', true)->where('s.discontinued', false)->where('s.source_type', 'Journal')
            ->first(['t.url', 't.field', 's.issn', 's.eissn']);
        if (! $target) return false;
        $ids = array_values(array_filter([$this->issn($target->issn), $this->issn($target->eissn)]));
        $journals = Journal::where('status', 'published')->whereIn('issn', $ids)->get();
        if ($journals->count() !== 1 || ! $this->sameSite($journals->first()->official_url, $target->url)) return false;
        return $this->fromRegisteredPage($journals->first()->id, $target->url, $target->field, $quote);
    }

    public function fromRegisteredPage(int $journalId, string $url, string $field, string $quote): bool
    {
        $journal = Journal::whereKey($journalId)->where('status', 'published')->first();
        if (! $journal || ! $this->sameSite($journal->official_url, $url)) return false;
        if ($field === 'scope') {
            if ($journal->scope_summary === $quote && $journal->scope_source_url === $url) return false;
            $this->catalog->save(['scope_summary' => $quote, 'scope_source_url' => $url,
                'checked_at' => now()->toDateString(), 'reuse_status' => 'crawler-source'], $journal);
            return true;
        }
        if (! in_array($field, ['fee', 'time'], true)) return false;
        $value = mb_substr($quote, 0, 255);
        if (DB::table('journal_observations')->where('journal_id', $journalId)->where('kind', $field)
            ->where('source_url', $url)->where('value', $value)->whereNull('retired_at')->exists()) return false;
        DB::transaction(function () use ($journalId, $field, $url, $value) {
            DB::table('journal_observations')->where('journal_id', $journalId)->where('kind', $field)
                ->where('source_url', $url)->where('verification', 'publisher_claim')->whereNull('retired_at')
                ->update(['retired_at' => now(), 'updated_at' => now()]);
            DB::table('journal_observations')->insert(['journal_id' => $journalId, 'kind' => $field,
                'label' => $field === 'fee' ? 'Biaya menurut situs jurnal' : 'Waktu menurut situs jurnal',
                'value' => $value, 'verification' => 'publisher_claim', 'source_url' => $url,
                'checked_at' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        });
        return true;
    }

    private function issn(?string $raw): ?string
    {
        $value = strtoupper(str_replace('-', '', trim((string) $raw)));
        if (! preg_match('/^[0-9]{7}[0-9X]$/D', $value)) return null;
        $formatted = substr($value, 0, 4).'-'.substr($value, 4);
        $valid = true;
        (new ValidIssn)->validate('issn', $formatted, function () use (&$valid) { $valid = false; });
        return $valid ? $formatted : null;
    }

    private function sameSite(?string $official, string $evidence): bool
    {
        try {
            return $official && $this->fetcher->host(preg_replace('/^http:/i', 'https:', $official))
                === $this->fetcher->host($evidence);
        } catch (\Throwable) {
            return false;
        }
    }
}
