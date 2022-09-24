<?php

namespace GrapheneICT\JwtGuard\Services;

use App\Services\Auth\JwtGuard;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class CognitoAuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cognito-auth.php' => config_path('cognito-auth.php'),
            ], 'config');
        }
    }

    /**
     * Register services
     */
    public function register()
    {
        $this->app->singleton(JwkConverter::class, function (Application $app) {
            return new JwkConverter();
        });

        $this->app->singleton(JwtService::class, function (Application $app) {
            return new JwtService();
        });

        $this->app->singleton(JwtGuard::class, function (Application $app) {
            return new JwtGuard($app['request']);
        });

        $this->app['auth']->extend('cognito', function ($app, $name, array $config) {
            return $app->make(JwtGuard::class);
        });
    }
}
