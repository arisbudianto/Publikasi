<?php

return [
    'enabled' => env('SCOPUS_CRAWL_ENABLED', false),
    'daily_limit' => min(10, max(1, (int) env('SCOPUS_CRAWL_DAILY_LIMIT', 5))),
    'recrawl_days' => 30,
];
