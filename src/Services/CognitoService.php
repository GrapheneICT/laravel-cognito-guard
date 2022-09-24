<?php

namespace GrapheneICT\CognitoGuard\Services;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;
use ErrorException;

class CognitoService
{
    /*
     * CognitoIdentityProviderClient
     */
    protected $cognitoIdentity;

    /**
     * CognitoService constructor.
     */
    public function __construct()
    {
        $this->cognitoIdentity = new CognitoIdentityProviderClient([
            'region' => env('AWS_COGNITO_REGION'),
            'version' => env('AWS_COGNITO_VERSION'),
        ]);
    }

    /**
     *  Returns or creates user from/to our database
     *
     * @param $cognitoId
     * @param $token
     * @return mixed
     *
     * @throws ErrorException
     */
    public function getOrCreateUser($cognitoId, $token)
    {
        if ($this->isCognitoUserSync($cognitoId)) {
            return config('cognito-auth.models.user.model')::where('provider_id', $cognitoId)
                ->first();
        }

        $attributes = $this->getCognitoUserAttributes($token);

        if (isset($attributes['identities'])) {
            $provider = json_decode($attributes['identities'])[0]->providerName;
        } else {
            $provider = 'cognito';
        }

        return config('cognito-auth.models.user.model')::create([
            'name' => $attributes['username'],
            'email' => $attributes['email'],
            'provider' => $provider,
            'provider_id' => $cognitoId,
        ]);
    }

    /**
     * Gets the user data from the Cognito Identity Provider
     *
     * @throws ErrorException
     */
    public function getCognitoUserAttributes($token): array
    {
        try {
            $attributes = $this->cognitoIdentity->getUser(['AccessToken' => $token]);
            $results['username'] = $attributes->get('Username');

            foreach ($attributes->get('UserAttributes') as $attributes) {
                $results[$attributes['Name']] = $attributes['Value'];
            }

            return $results;
        } catch (AwsException $e) {
            throw new ErrorException($e->getMessage());
        }
    }

    /**
     * Checks if the cognito user is already in our database
     *
     * @param $cognitoId
     * @return bool
     */
    private function isCognitoUserSync($cognitoId): bool
    {
        return config('cognito-auth.models.user.model')::where('provider_id', $cognitoId)
            ->exists();
    }
}
