<?php

return [
    'enabled' => env('SEMANTIC_AI_ENABLED', false),
    'key' => env('DINOIKI_API_KEY'),
    'model' => 'text-embedding-3-small',
    'endpoint' => 'https://ai.dinoiki.com/v1/embeddings',
    'daily_limit' => 100,
    'catalog_limit' => (int) env('SEMANTIC_CATALOG_LIMIT', 50),
    'embedding_batch_size' => 50,
];
