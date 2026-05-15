<?php

use Illuminate\Foundation\Console\AboutCommand;

it('registers a Cognito Guard section in php artisan about', function () {
    if (! class_exists(AboutCommand::class)) {
        $this->markTestSkipped('AboutCommand not available in this Laravel version.');
    }

    $this->artisan('about', ['--only' => 'Cognito Guard'])
        ->assertExitCode(0)
        ->expectsOutputToContain('Default pool')
        ->expectsOutputToContain('default')
        ->expectsOutputToContain('Groups → Gates bridge');
});
