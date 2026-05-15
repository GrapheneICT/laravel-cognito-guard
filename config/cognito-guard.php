<?php

use App\Models\User;

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
        'model' => env('COGNITO_USER_MODEL', User::class),
        'sub_column' => 'provider_id',
        'attribute_map' => [
            'email' => 'email',
            'cognito:username' => 'name',
        ],
    ],

    'bridge_groups_to_gates' => (bool) env('COGNITO_BRIDGE_GROUPS_TO_GATES', true),

];
