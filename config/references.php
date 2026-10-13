<?php

return [
    'user_daily_limit' => (int) env('REFERENCE_USER_DAILY_LIMIT', 200),
    'global_daily_limit' => (int) env('REFERENCE_GLOBAL_DAILY_LIMIT', 1000),
];
