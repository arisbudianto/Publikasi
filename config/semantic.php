<?php

return [
    'enabled' => env('SEMANTIC_AI_ENABLED', false),
    'key' => env('DINOIKI_API_KEY'),
    'model' => 'text-embedding-3-small',
    'endpoint' => 'https://ai.dinoiki.com/v1/embeddings',
    'daily_limit' => 100,
    'catalog_limit' => 50,
];
