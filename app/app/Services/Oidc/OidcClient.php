<?php

namespace App\Services\Oidc;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Thin, provider-agnostic OIDC client. Deliberately does not hard-code
 * anything Microsoft-specific — any standards-compliant OIDC issuer works as
 * long as it publishes a discovery document.
 *
 * Identity is established via the userinfo endpoint (a direct, TLS,
 * confidential-client HTTPS call using the just-issued access token) rather
 * than by locally verifying the id_token's JWT signature. Given the
 * authorization code was itself only exchangeable by this confidential
 * client over TLS, this is an accepted simplification that avoids taking on
 * a JWKS-verification dependency; it trades a small amount of defense-in-depth
 * for materially simpler, more auditable code.
 */
class OidcClient
{
    public function __construct(private readonly OidcDiscoveryService $discovery) {}

    public function provider(): GenericProvider
    {
        $endpoints = $this->discovery->discover(config('oidc.issuer'));

        return new GenericProvider([
            'clientId' => config('oidc.client_id'),
            'clientSecret' => config('oidc.client_secret'),
            'redirectUri' => config('oidc.redirect_uri'),
            'urlAuthorize' => $endpoints['authorization_endpoint'],
            'urlAccessToken' => $endpoints['token_endpoint'],
            'urlResourceOwnerDetails' => $endpoints['userinfo_endpoint'],
            // league/oauth2-client's GenericProvider joins multi-scope arrays
            // with a comma by default, which is not valid for OIDC (RFC 6749 /
            // the OIDC core spec require a space-separated scope string). Left
            // at the default, providers see one garbled scope token instead of
            // "openid profile email" and reject the request outright.
            'scopeSeparator' => ' ',
        ]);
    }

    /**
     * @param  array<string, string>  $extra  Extra authorization params, e.g.
     *                                        ['prompt' => 'none'] for a silent
     *                                        (no-UI) sign-in attempt.
     * @return array{0: string, 1: string} [authorization URL, state]
     */
    public function authorizationUrl(array $extra = []): array
    {
        $provider = $this->provider();

        $url = $provider->getAuthorizationUrl([
            'scope' => config('oidc.scopes'),
        ] + $extra);

        return [$url, $provider->getState()];
    }

    public function exchangeCode(string $code): AccessTokenInterface
    {
        return $this->provider()->getAccessToken('authorization_code', ['code' => $code]);
    }

    /**
     * @return array{sub: string, email: ?string, name: ?string}
     */
    public function claims(AccessTokenInterface $token): array
    {
        $owner = $this->provider()->getResourceOwner($token)->toArray();

        return [
            'sub' => (string) ($owner['sub'] ?? ''),
            'email' => $owner['email'] ?? $owner['preferred_username'] ?? null,
            'name' => $owner['name'] ?? $owner['email'] ?? null,
        ];
    }
}
