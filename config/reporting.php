<?php

return [
    'enabled' => true,
    // Only this one-way verifier is deployed. The bearer token stays on the owner's computer.
    'token_sha256' => 'bfba52ed0fb1990c0679afec5f4c15ef6c2a661e0c3dbf66d95ed04d6c31118e',
    // Remains valid until explicitly rotated or disabled; no recurring setup step.
    'token_expires_at' => null,
    'max_seconds' => 240,
    'chunk_size' => 500,
];
