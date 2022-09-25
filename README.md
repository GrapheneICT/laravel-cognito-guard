# Laravel Cognito Guard

[![Latest Version on Packagist](https://img.shields.io/packagist/v/grapheneict/graphene-ict-laravel-cognito-guard.svg?style=flat-square)](https://packagist.org/packages/grapheneict/graphene-ict-laravel-cognito-guard)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/grapheneict/graphene-ict-laravel-cognito-guard/run-tests?label=tests)](https://github.com/grapheneict/graphene-ict-laravel-cognito-guard/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/grapheneict/graphene-ict-laravel-cognito-guard/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/grapheneict/graphene-ict-laravel-cognito-guard/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/grapheneict/graphene-ict-laravel-cognito-guard.svg?style=flat-square)](https://packagist.org/packages/grapheneict/graphene-ict-laravel-cognito-guard)

Laravel authentication guard to validate JSON Web Tokens (JWT) issued by an AWS Cognito User Pool

## Installation

You can install the package via composer:

```bash
composer require graphene-ict/laravel-cognito-guard
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="GrapheneICT\CognitoGuard\Services\CognitoAuthServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php
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
```

Since `persist_user_data` is `true` by default user will be automatically saved with the following attributes name, email, provider and provider_id so
adding them in the list of fillables is a must. If you wish to extend with more attributes modify the data before it is saved or use the events and
use class `CognitoService` to retrieve them from the Cognito by providing a request token.

```php
   $cognitoService = new CognitoService();
   $attributes = $cognitoService->getCognitoUserAttributes($token);
```

## Usage

In  config`auth` create additional guard with the coginto driver

```php
   'api' => [
            'driver' => 'cognito',
            'provider' => 'users',
        ],
```

After that just apply it to the Authentication Defaults as option for authentication shown bellow

```php
    'defaults' => [
        'guard' => 'api',
        'passwords' => 'users',
    ],
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jovan Stojiljkovic](https://github.com/GrapheneICT)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
