<?php

return [

    'service_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:3100'),

    'service_key' => env('AI_SERVICE_KEY', 'banwohub-ai-dev-key'),

    'stub_mode' => env('AI_STUB_MODE', false),

    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 30),

    'public_rate_limit_per_minute' => (int) env('AI_PUBLIC_RATE_LIMIT_PER_MINUTE', 10),

    'public_organization_id' => env('AI_PUBLIC_ORGANIZATION_ID'),

    'public_disclaimer' => 'This AI assistant provides general information only — not legal advice. '
        .'For advice about your situation, please contact Banwolaw to schedule a consultation with a qualified attorney.',

    'disclaimer' => 'AI-generated content is for assistance only and must be reviewed by a qualified legal professional before use.',

    'label' => 'AI-generated',

    'review_statuses' => [
        'generated',
        'under_review',
        'edited',
        'approved',
        'rejected',
        'finalized',
    ],

    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'description' => 'GPT models via OpenAI API.',
            'default_model' => env('AI_OPENAI_DEFAULT_MODEL', 'gpt-4o-mini'),
            'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'],
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'description' => 'Claude models via Anthropic API.',
            'default_model' => env('AI_ANTHROPIC_DEFAULT_MODEL', 'claude-3-5-haiku-20241022'),
            'models' => ['claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022', 'claude-3-opus-20240229'],
        ],
        'google' => [
            'label' => 'Google AI',
            'description' => 'Gemini models via Google AI API.',
            'default_model' => env('AI_GOOGLE_DEFAULT_MODEL', 'gemini-2.0-flash'),
            'models' => ['gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'],
        ],
        'deepseek' => [
            'label' => 'Deepseek',
            'description' => 'Deepseek chat models via Deepseek API.',
            'default_model' => env('AI_DEEPSEEK_DEFAULT_MODEL', 'deepseek-chat'),
            'models' => ['deepseek-chat', 'deepseek-reasoner'],
        ],
    ],

];
