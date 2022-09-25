<?php

namespace GrapheneICT\CognitoGuard\Tests\Unit;

use ErrorException;
use Firebase\JWT\JWK;
use GrapheneICT\CognitoGuard\Services\Auth\JwtGuard;
use GrapheneICT\CognitoGuard\Services\JwkConverter;
use GrapheneICT\CognitoGuard\Tests\TestCase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

class JwtGuardTest extends TestCase
{
    /**
     * @test
     */
    public function testGuardAuthorizesUser()
    {
        $jtb = $this->getJwtTestBundle();
        $issuer = $this->getIssuer();

        Route::get('test', function () {
            return auth()->user();
        })->middleware(SubstituteBindings::class)->middleware('auth');

        $this->mock(JwkConverter::class, function ($mock) use ($jtb, $issuer) {
            $mock->shouldReceive('getJwks')
                ->andReturn(JWK::parseKeySet($jtb->jwks, 'RSA256'));
            $mock->shouldReceive('getIssuer')
                ->andReturn($issuer);
        });

        $this->getJson('/test', [
            'Authorization' => 'Bearer' . ' ' . $jtb->jwt,
        ])->assertSuccessful()
            ->assertJsonFragment([
                'sub' => $jtb->sub
            ]);
    }

    /**
     * @test
     */
    public function testValidateThrowsAnException()
    {
        $guard = $this->app->make(JwtGuard::class);

        $this->expectException(ErrorException ::class);
        $this->expectExceptionMessage('JwtGuard::validate() not implemented by design.');
        $guard->validate();
    }
}
