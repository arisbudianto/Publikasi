<?php

namespace App\Services;

use Illuminate\Validation\Rule;

class StructuredObservation
{
    public const FIELDS = [
        'journal_issn', 'kind', 'label', 'value', 'verification', 'source_url', 'checked_at', 'period',
        'metric_scheme', 'rank_value', 'amount_minor', 'currency', 'fee_route', 'waiver_available',
        'language', 'frequency_per_year', 'effective_from', 'effective_to', 'review_note',
    ];

    public static function rules(bool $requireConfirmation = true, bool $requireStructured = false): array
    {
        $feeRequired = $requireStructured ? '|required_if:kind,fee' : '';
        $rules = [
            'kind' => ['required', Rule::in(['index', 'fee', 'time', 'language', 'frequency'])],
            'label' => 'required|string|max:120',
            'value' => ['required', 'string', 'max:255', Rule::notIn(['unknown', 'Belum diketahui', 'belum diketahui'])],
            'verification' => ['required', Rule::in(['publisher_claim', 'verified'])],
            'source_url' => 'required|url:https|max:1000',
            'checked_at' => 'required|date_format:Y-m-d|before_or_equal:today',
            'period' => 'nullable|string|max:120',
            'metric_scheme' => ['nullable', 'string', 'max:80', 'required_with:rank_value', 'prohibited_unless:kind,index'],
            'rank_value' => ['nullable', 'integer', 'min:1', 'max:100', 'required_with:metric_scheme', 'prohibited_unless:kind,index'],
            'amount_minor' => 'nullable|integer|min:0|max:999999999999999'.$feeRequired.'|required_with:currency,fee_route|prohibited_unless:kind,fee',
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'.($requireStructured ? '|required_if:kind,fee' : ''), 'required_with:amount_minor,fee_route', 'prohibited_unless:kind,fee'],
            'fee_route' => array_merge(['nullable', Rule::in(['apc_oa', 'subscription', 'submission_fee', 'other']), 'required_with:amount_minor,currency'], $requireStructured ? ['required_if:kind,fee'] : [], ['prohibited_unless:kind,fee']),
            'waiver_available' => 'nullable|boolean|prohibited_unless:kind,fee',
            'language' => 'nullable|string|max:100|required_if:kind,language|prohibited_unless:kind,language',
            'frequency_per_year' => 'nullable|integer|min:1|max:365|required_if:kind,frequency|prohibited_unless:kind,frequency',
            'effective_from' => 'nullable|date_format:Y-m-d',
            'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'review_note' => 'required|string|min:20|max:3000',
        ];

        if ($requireConfirmation) {
            $rules['confirmed'] = 'accepted';
        }

        return $rules;
    }

    public static function normalize(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);
            }
            if (($row[$key] ?? null) === '') {
                $row[$key] = null;
            }
        }

        if (isset($row['currency'])) {
            $row['currency'] = strtoupper($row['currency']);
        }

        if (isset($row['waiver_available'])) {
            $row['waiver_available'] = match (strtolower((string) $row['waiver_available'])) {
                '1', 'true', 'yes', 'ya' => true,
                '0', 'false', 'no', 'tidak' => false,
                default => $row['waiver_available'],
            };
        }

        return $row;
    }
}
