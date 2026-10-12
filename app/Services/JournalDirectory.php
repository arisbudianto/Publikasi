<?php

namespace App\Services;

use App\Models\JournalObservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalDirectory
{
    public function options(): array
    {
        $current = $this->currentObservations(JournalObservation::query());

        return [
            'rankSchemes' => (clone $current)->where('kind', 'index')->whereNotNull('metric_scheme')->distinct()->orderBy('metric_scheme')->pluck('metric_scheme'),
            'languages' => (clone $current)->where('kind', 'language')->whereNotNull('language')->distinct()->orderBy('language')->pluck('language'),
            'currencies' => (clone $current)->where('kind', 'fee')->where('fee_route', 'apc_oa')->whereNotNull('amount_minor')->whereNotNull('currency')->distinct()->orderBy('currency')->pluck('currency'),
        ];
    }

    public function currentObservations(Builder|HasMany $query): Builder|HasMany
    {
        $today = today()->toDateString();

        return $query->whereNull('retired_at')
            ->where(fn (Builder $dates) => $dates->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn (Builder $dates) => $dates->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today));
    }

    public function search(Builder $query, array $filters): Builder
    {
        $term = trim($filters['q'] ?? '');

        return $query
            ->when($term !== '', fn (Builder $journals) => $journals->where(fn (Builder $match) => $match
                ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('issn', $term)
                ->orWhereHas('identifiers', fn (Builder $identifier) => $identifier->where('value', strtoupper($term))))
            )
            ->when(! empty($filters['subject']), fn (Builder $journals) => $journals->where('subject', $filters['subject']))
            ->when(! empty($filters['publisher']), fn (Builder $journals) => $journals->where('publisher', $filters['publisher']))
            ->when(! empty($filters['language']), fn (Builder $journals) => $journals->whereHas('observations', fn (Builder $observation) => $this->currentObservations($observation)->where('kind', 'language')->where('language', $filters['language'])));
    }

    public function filter(Builder $query, array $filters): Builder
    {
        $verification = $filters['verification'] ?? null;
        $hasMax = isset($filters['apc_max_minor']) && $filters['apc_max_minor'] !== '';
        $hasRank = ! empty($filters['rank_scheme']) || (isset($filters['rank_value']) && $filters['rank_value'] !== '') || ! empty($filters['indexation']);
        $hasFrequency = (isset($filters['frequency_min']) && $filters['frequency_min'] !== '') || (isset($filters['frequency_max']) && $filters['frequency_max'] !== '');

        if ($verification && ! $hasRank && empty($filters['fee_route']) && ! $hasMax && empty($filters['language']) && ! $hasFrequency) {
            $query->whereHas('observations', fn (Builder $observation) => $this->currentObservations($observation)->where('verification', $verification));
        }

        if ($hasRank) {
            $query->whereHas('observations', function (Builder $observation) use ($filters, $verification) {
                $this->currentObservations($observation)->where('kind', 'index');
                if ($verification) {
                    $observation->where('verification', $verification);
                }
                if (! empty($filters['rank_scheme'])) {
                    $observation->where('metric_scheme', $filters['rank_scheme']);
                }
                if (isset($filters['rank_value']) && $filters['rank_value'] !== '') {
                    $observation->where('rank_value', (int) $filters['rank_value']);
                }
                if (! empty($filters['indexation'])) {
                    $term = '%'.addcslashes($filters['indexation'], '%_\\').'%';
                    $observation->where(fn (Builder $match) => $match->where('label', 'like', $term)->orWhere('value', 'like', $term));
                }
            });
        }

        if (! empty($filters['fee_route']) || $hasMax) {
            $query->whereHas('observations', function (Builder $observation) use ($filters, $verification, $hasMax) {
                $this->currentObservations($observation)->where('kind', 'fee');
                if ($verification) {
                    $observation->where('verification', $verification);
                }
                if (! empty($filters['fee_route'])) {
                    $observation->where('fee_route', $filters['fee_route']);
                }
                if ($hasMax) {
                    $observation->where('fee_route', 'apc_oa')->where('currency', $filters['apc_currency'])
                        ->whereNotNull('amount_minor')->where('amount_minor', '<=', (int) $filters['apc_max_minor']);
                }
            });
        }

        if ($hasFrequency) {
            $query->whereHas('observations', function (Builder $observation) use ($filters, $verification) {
                $this->currentObservations($observation)->where('kind', 'frequency')->whereNotNull('frequency_per_year');
                if ($verification) {
                    $observation->where('verification', $verification);
                }
                if (isset($filters['frequency_min']) && $filters['frequency_min'] !== '') {
                    $observation->where('frequency_per_year', '>=', (int) $filters['frequency_min']);
                }
                if (isset($filters['frequency_max']) && $filters['frequency_max'] !== '') {
                    $observation->where('frequency_per_year', '<=', (int) $filters['frequency_max']);
                }
            });
        }

        return $query;
    }

    public function sort(Builder $query, array $filters): Builder
    {
        $sort = $filters['sort'] ?? 'name';
        $verification = $filters['verification'] ?? 'verified';

        if ($sort === 'rank') {
            $rank = $this->currentObservations(JournalObservation::query())
                ->select('rank_value')->whereColumn('journal_id', 'journals.id')->where('kind', 'index')
                ->where('verification', $verification)->where('metric_scheme', $filters['rank_scheme'])->whereNotNull('rank_value')
                ->orderByDesc('checked_at')->limit(1);
            $query->addSelect(['directory_sort_value' => $rank])->orderByRaw('CASE WHEN directory_sort_value IS NULL THEN 1 ELSE 0 END')->orderBy('directory_sort_value')->orderBy('name');
        } elseif ($sort === 'cost') {
            $cost = $this->currentObservations(JournalObservation::query())
                ->select('amount_minor')->whereColumn('journal_id', 'journals.id')->where('kind', 'fee')
                ->where('verification', $verification)->where('fee_route', 'apc_oa')->where('currency', $filters['apc_currency'])->whereNotNull('amount_minor')
                ->orderBy('amount_minor')->orderByDesc('checked_at')->limit(1);
            $query->addSelect(['directory_sort_value' => $cost])->orderByRaw('CASE WHEN directory_sort_value IS NULL THEN 1 ELSE 0 END')->orderBy('directory_sort_value')->orderBy('name');
        } elseif ($sort === 'checked') {
            $checked = $this->currentObservations(JournalObservation::query())->select('checked_at')
                ->whereColumn('journal_id', 'journals.id')->whereNotNull('checked_at')->orderByDesc('checked_at')->limit(1);
            $query->addSelect(['directory_sort_value' => $checked])->orderByRaw('CASE WHEN directory_sort_value IS NULL THEN 1 ELSE 0 END')->orderByDesc('directory_sort_value')->orderBy('name');
        } else {
            $query->orderBy('name');
        }

        return $query;
    }
}
