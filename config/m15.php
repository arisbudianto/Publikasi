<?php

return [
    'enabled' => env('M15_ENABLED', true),
    'chat_endpoint' => 'https://ai.dinoiki.com/v1/chat/completions',
    'chat_model' => env('M15_CHAT_MODEL', 'gpt-5-mini'),
    'monthly_budget_usd' => (float) env('M15_MONTHLY_BUDGET_USD', 5),
    'monthly_analysis_limit' => (int) env('M15_MONTHLY_ANALYSIS_LIMIT', 20),
    'user_daily_limit' => (int) env('M15_USER_DAILY_LIMIT', 2),
    'global_daily_limit' => (int) env('M15_GLOBAL_DAILY_LIMIT', 4),
    'candidate_limit' => 50,
    'result_limit' => 10,
    'embedding_model' => 'text-embedding-3-small',
    'embedding_endpoint' => 'https://ai.dinoiki.com/v1/embeddings',
    'input_price_per_million' => (float) env('M15_CHAT_INPUT_USD_PER_MILLION', 0.25),
    'output_price_per_million' => (float) env('M15_CHAT_OUTPUT_USD_PER_MILLION', 2.00),
    'embedding_price_per_million' => (float) env('M15_EMBEDDING_USD_PER_MILLION', 0.02),
];
