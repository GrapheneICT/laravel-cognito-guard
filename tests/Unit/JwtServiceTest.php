<?php

namespace GrapheneICT\CognitoGuard\Tests\Unit;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GrapheneICT\CognitoGuard\Exceptions\InvalidTokenException;
use GrapheneICT\CognitoGuard\Services\JwkConverter;
use GrapheneICT\CognitoGuard\Services\JwtService;
use GrapheneICT\CognitoGuard\Tests\TestCase;
use Illuminate\Contracts\Container\BindingResolutionException;

class JwtServiceTest extends TestCase
{
    /**
     * @test
     *
     * @throws InvalidTokenException
     * @throws BindingResolutionException
     */
    public function testDecode()
    {
        $jtb = $this->getJwtTestBundle();
        $issuer = $this->getIssuer();

        $this->mock(JwkConverter::class, function ($mock) use ($jtb, $issuer) {
            $mock->shouldReceive('getJwks')
                ->andReturn(JWK::parseKeySet($jtb->jwks, 'RSA256'));
            $mock->shouldReceive('getIssuer')
                ->andReturn($issuer);
        });

        $jwtService = new JwtService();

        $result = $jwtService->decode($jtb->jwt);

        $this->assertEquals($jtb->sub, $result->sub);
        $this->assertEquals($jtb->sub, $result->username);
    }

    /**
     * @test
     */
    public function testValidateHeader()
    {
        $jtb = $this->getJwtTestBundle();
        $ts = $this->app->make(JwtService::class);
        $ts->getKid($jtb->jwt);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @test
     */
    public function testValidateHeaderFailsIfWrongSegments()
    {
        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token has a wrong number of segments');
        $ts->getKid('INVALID_TOKEN');
    }

    /**
     * @test
     */
    public function testValidateHeaderFailsIfNotJson()
    {
        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Syntax error, malformed JSON');
        $ts->getKid('IN.VALID.TOKEN');
    }

    /**
     * @test
     */
    public function testValidateHeaderFailsIfNotB64()
    {
        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Malformed UTF-8 characters');
        $ts->getKid(json_encode(['kid' => '123']).'.seg2.seg3');
    }

    /**
     * @test
     */
    public function testValidateHeaderFailsIfNoAlg()
    {
        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('No alg present in token header');
        $ts->getKid(base64_encode(json_encode(['kid' => '123'])).'.seg2.seg3');
    }

    /**
     * @test
     */
    public function testValidateHeaderFailsIfAlgNotRS256()
    {
        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('The token alg is not RS256');
        $ts->getKid(base64_encode(json_encode(['kid' => '123', 'alg' => 'other'])).'.seg2.seg3');
    }

    /**
     * @test
     */
    public function testValidateHeaderFailsIfNoKid()
    {
        $jtb = $this->getJwtTestBundle();
        $jwtNoKid = JWT::encode($jtb->payload, $jtb->keypair, 'RS256');
        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('No kid present in token header');
        $ts->getKid($jwtNoKid);
    }

    /**
     * @test
     */
    public function testValidatePayloadFailsIfIssuerDoesntMatch()
    {
        $jtb = $this->getJwtTestBundle();
        $jtb->payload['iss'] = 'WRONG_ISSUER';

        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Invalid issuer');
        $ts->validatePayload((object) $jtb->payload, $this->getIssuer());
    }

    /**
     * @test
     */
    public function testValidatePayloadFailsIfIncorrectTokenUse()
    {
        $jtb = $this->getJwtTestBundle();

        $jtb->payload['token_use'] = 'WRONG_USE';

        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Invalid token_use');
        $ts->validatePayload((object) $jtb->payload, $this->getIssuer());
    }

    /**
     * @test
     */
    public function testValidatePayloadFailsIfNoUsername()
    {
        $jtb = $this->getJwtTestBundle();
        unset($jtb->payload['username']);

        $ts = $this->app->make(JwtService::class);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Invalid token attributes. Token must include one of "username","cognito:username"');
        $ts->validatePayload((object) $jtb->payload, $this->getIssuer());
    }
}
