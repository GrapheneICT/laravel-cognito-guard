<?php

return [

    'default_pool' => env('COGNITO_DEFAULT_POOL', 'default'),

    'pools' => [
        'default' => [
            'user_pool_id' => env('COGNITO_USER_POOL_ID'),
            'region' => env('AWS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'allowed_token_use' => ['access', 'id'],
            'allowed_client_ids' => array_values(array_filter(
                array_map('trim', explode(',', (string) env('COGNITO_CLIENT_IDS', ''))),
            )),
            'required_scopes' => [],
            'leeway' => 0,
        ],
    ],

    'jwks' => [
        'cache_store' => env('COGNITO_JWKS_CACHE_STORE'),
        'cache_ttl' => (int) env('COGNITO_JWKS_CACHE_TTL', 21600),
        'cache_key_prefix' => 'cognito-guard:jwks',
        'stale_on_error' => true,
        'http_timeout' => 5,
    ],

    'user_provider' => [
        'auto_provision' => (bool) env('COGNITO_AUTO_PROVISION', true),
        'model' => env('COGNITO_USER_MODEL', 'App\\Models\\User'),

        // Which JWT claim supplies the stable identifier. Default `sub` works for
        // almost everyone. Override when a legacy users table is keyed by
        // `cognito:username` or a custom attribute instead of the Cognito sub.
        'sub_claim' => env('COGNITO_SUB_CLAIM', 'sub'),

        // Which column on the user model stores the identifier value above.
        'sub_column' => 'provider_id',

        'attribute_map' => [
            'email' => 'email',
            'cognito:username' => 'name',
        ],
    ],

    'bridge_groups_to_gates' => (bool) env('COGNITO_BRIDGE_GROUPS_TO_GATES', true),

    'log' => [
        // Channel name (from config/logging.php) to write package events to.
        // null = the app default. Set to a dedicated channel for clean separation.
        'channel' => env('COGNITO_LOG_CHANNEL'),

        // Master switch. Disable to silence all package-side logging.
        'enabled' => (bool) env('COGNITO_LOG_ENABLED', true),
    ],

];
