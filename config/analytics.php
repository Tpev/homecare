<?php

return [
    'anon_cookie_name' => env('ANALYTICS_ANON_COOKIE', 'hc_anon_id'),
    'anon_cookie_days' => (int) env('ANALYTICS_ANON_COOKIE_DAYS', 1825),

    // Exclude these accounts only from the admin usage dashboard and its reports.
    'usage_excluded_emails' => [
        'barlsey42@gmail.com',
    ],
];
