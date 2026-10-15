<?php

return [
    'enabled' => env('SINTA_CRAWL_ENABLED', false),
    'daily_limit' => min(20, max(1, (int) env('SINTA_CRAWL_DAILY_LIMIT', 15))),
    'recrawl_days' => 60,
];
