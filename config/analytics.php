<?php

return [
    'anon_cookie_name' => env('ANALYTICS_ANON_COOKIE', 'hc_anon_id'),
    'anon_cookie_days' => (int) env('ANALYTICS_ANON_COOKIE_DAYS', 1825),
];

