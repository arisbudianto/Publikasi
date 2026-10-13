<?php

return [
    'enabled' => (bool) env('M16_ENABLED', false),
    'max_topics' => 3,
    'max_versions' => 5,
    'requests_per_month' => 60,
    'requests_per_run' => 5,
    'interval_hours' => 24,
    'cron_fresh_hours' => 36,
    'match_threshold' => 0.25,
];
