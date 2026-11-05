<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OneStall Marketplace AI Engine Configuration & Feature Flags
    |--------------------------------------------------------------------------
    |
    | All AI capabilities operate behind strict interfaces, feature flags,
    | multi-tenant seller scopes, and complete audit logging.
    |
    */

    'enabled' => env('AI_ENABLED', true),

    // Active AI Driver ('rule_based', 'openai', 'gemini', 'mock')
    'default_driver' => env('AI_DRIVER', 'rule_based'),

    // Granular Feature Flags (Can also be overridden dynamically via DB Settings)
    'features' => [
        'semantic_search' => (bool) env('AI_FEATURE_SEMANTIC_SEARCH', true),
        'recommendations' => (bool) env('AI_FEATURE_RECOMMENDATIONS', true),
        'seller_assistant' => (bool) env('AI_FEATURE_SELLER_ASSISTANT', true),
        'demand_forecasting' => (bool) env('AI_FEATURE_DEMAND_FORECASTING', true),
        'fraud_scoring' => (bool) env('AI_FEATURE_FRAUD_SCORING', true),
        'support_replies' => (bool) env('AI_FEATURE_SUPPORT_REPLIES', true),
    ],

    // Third-party LLM Provider Keys (Optional when using LLM drivers)
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        ],
    ],

    // Order Fraud Risk Assessment Engine Defaults
    'fraud' => [
        'high_risk_threshold' => (int) env('AI_FRAUD_HIGH_RISK_THRESHOLD', 70),
        'medium_risk_threshold' => (int) env('AI_FRAUD_MEDIUM_RISK_THRESHOLD', 35),
        'velocity_max_orders_1h' => (int) env('AI_FRAUD_MAX_ORDERS_1H', 5),
        'high_value_cod_threshold' => (float) env('AI_FRAUD_HIGH_VALUE_COD', 3000.00),
    ],

    // Automated Support Replies
    'support' => [
        'auto_send_confidence_threshold' => (float) env('AI_SUPPORT_CONFIDENCE_THRESHOLD', 0.80),
        'enable_auto_send' => (bool) env('AI_SUPPORT_ENABLE_AUTO_SEND', true),
    ],

    // Logging & Audit
    'logging' => [
        'enabled' => true,
        'log_payloads' => true,
    ],
];
