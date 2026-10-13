<?php

return [
    'enabled' => env('M19_ENABLED', false),
    'batch_limit' => 5,
    'max_bytes' => 262144,
    'user_agent' => 'PublikasiJournalMonitor/1.0 (+https://publikasi.speakverse.id)',
    'ai_enabled' => env('M19_AI_ENABLED', false),
    'ai_monthly_limit' => (int) env('M19_AI_MONTHLY_LIMIT', 10),
    'ai_max_completion_tokens' => (int) env('M19_AI_MAX_COMPLETION_TOKENS', 2000),
];
