<?php

return [
    'version' => 'ai-support-model-candidates-v1',
    'pricing_checked_on' => '2026-08-14',
    'currency' => 'USD',
    'candidates' => [
        [
            'id' => 'gpt-5-nano-low',
            'provider' => 'openai',
            'model' => 'gpt-5-nano-2025-08-07',
            'endpoint' => 'responses',
            'reasoning_effort' => 'low',
            'max_output_tokens' => 600,
            'baseline_eligible' => false,
            'pricing_per_million_tokens' => [
                'input' => 0.05,
                'cached_input' => 0.005,
                'output' => 0.40,
            ],
            'source_url' => 'https://developers.openai.com/api/docs/models/gpt-5-nano',
            'purpose' => 'Deprecated absolute price-floor benchmark; not eligible for baseline recommendation.',
        ],
        [
            'id' => 'gpt-5.6-luna-low',
            'provider' => 'openai',
            'model' => 'gpt-5.6-luna',
            'endpoint' => 'responses',
            'reasoning_effort' => 'low',
            'max_output_tokens' => 600,
            'baseline_eligible' => true,
            'pricing_per_million_tokens' => [
                'input' => 0.20,
                'cached_input' => 0.02,
                'output' => 1.20,
            ],
            'source_url' => 'https://developers.openai.com/api/docs/models/gpt-5.6-luna',
            'purpose' => 'Current cost-sensitive nano-tier candidate.',
        ],
        [
            'id' => 'gpt-5.4-mini-low',
            'provider' => 'openai',
            'model' => 'gpt-5.4-mini-2026-03-17',
            'endpoint' => 'responses',
            'reasoning_effort' => 'low',
            'max_output_tokens' => 600,
            'baseline_eligible' => true,
            'pricing_per_million_tokens' => [
                'input' => 0.75,
                'cached_input' => 0.075,
                'output' => 4.50,
            ],
            'source_url' => 'https://developers.openai.com/api/docs/models/gpt-5.4-mini',
            'purpose' => 'Higher-capability fallback challenger.',
        ],
    ],
];
