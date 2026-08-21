<?php

use App\Models\ContentApiToken;

$issuer = rtrim((string) env('APP_URL', 'https://carelolo.com'), '/');
$resource = rtrim((string) env('CONTENT_MCP_PUBLIC_URL', $issuer.'/mcp/content'), '/');

return [
    'issuer' => $issuer,
    'resource' => $resource,
    'service_port' => max(1024, min(65535, (int) env('CONTENT_MCP_SERVICE_PORT', 8090))),
    'authorization_code_ttl_minutes' => max(1, min(10, (int) env('CONTENT_MCP_AUTH_CODE_TTL_MINUTES', 5))),
    'access_token_ttl_minutes' => max(5, min(60, (int) env('CONTENT_MCP_ACCESS_TOKEN_TTL_MINUTES', 15))),
    'refresh_token_ttl_days' => max(1, min(90, (int) env('CONTENT_MCP_REFRESH_TOKEN_TTL_DAYS', 30))),
    'dynamic_client_ttl_days' => max(7, min(365, (int) env('CONTENT_MCP_DYNAMIC_CLIENT_TTL_DAYS', 90))),
    'registration_rate_limit_per_hour' => max(1, (int) env('CONTENT_MCP_REGISTRATION_RATE_LIMIT_PER_HOUR', 10)),
    'token_rate_limit_per_minute' => max(1, (int) env('CONTENT_MCP_TOKEN_RATE_LIMIT_PER_MINUTE', 30)),
    'authorization_rate_limit_per_minute' => max(1, (int) env('CONTENT_MCP_AUTHORIZATION_RATE_LIMIT_PER_MINUTE', 20)),
    'introspection_rate_limit_per_minute' => max(1, (int) env('CONTENT_MCP_INTROSPECTION_RATE_LIMIT_PER_MINUTE', 120)),
    'scopes' => [
        ContentApiToken::ABILITY_READ => 'View CMS articles, readiness, authors, taxonomy, and managed media.',
        ContentApiToken::ABILITY_DRAFT => 'Create and edit article drafts and structured content.',
        ContentApiToken::ABILITY_MEDIA => 'Upload validated managed article media.',
        ContentApiToken::ABILITY_AUDIT => 'Run and attribute content audits and readiness checks.',
        ContentApiToken::ABILITY_SUBMIT => 'Submit an article for the configured editorial workflow.',
        ContentApiToken::ABILITY_SCHEDULE => 'Schedule a ready article for publication.',
        ContentApiToken::ABILITY_PUBLISH => 'Publish a ready article immediately.',
    ],
];
