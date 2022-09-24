<?php

return [
    /*
     * If persist_user_data is true the cognito guard will automatically create a new user
     * record anytime the user contained in a validated JWT
     * does not already exist in the users table.
     *
     * The new user will be created with the user attributes name, email, provider and provider_id so
     * it is required for you to add them at the list of fillable attributes in the model array, if you
     * wish to add more attributes from the cognito modify before it is saved or use the events.
     *
     */
    'persist_user_data' => true,

    'models' => [
        /*
         * When using this package, we need to know which
         * Eloquent model should be used for your user. Of course, it
         * is often just the "User" model but you may use whatever you like.
         *
         */
        'user' => [
            'model' => App\Models\User::class,
        ],
    ],
];
