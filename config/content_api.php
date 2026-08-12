<?php

return [
    'rate_limit_per_minute' => max(1, (int) env('CONTENT_API_RATE_LIMIT_PER_MINUTE', 60)),
    'authentication_failures_per_minute' => max(1, (int) env('CONTENT_API_AUTH_FAILURES_PER_MINUTE', 20)),
    'max_json_bytes' => max(1024, (int) env('CONTENT_API_MAX_JSON_BYTES', 1048576)),
    'max_media_bytes' => min(20 * 1024 * 1024, max(1024, (int) env('CONTENT_API_MAX_MEDIA_BYTES', 20971520))),
    'preview_ttl_minutes' => max(1, min(60, (int) env('CONTENT_API_PREVIEW_TTL_MINUTES', 15))),
    'idempotency_ttl_hours' => max(1, min(168, (int) env('CONTENT_API_IDEMPOTENCY_TTL_HOURS', 24))),
];
