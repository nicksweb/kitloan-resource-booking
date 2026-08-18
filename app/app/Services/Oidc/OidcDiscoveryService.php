<?php

namespace App\Services\Oidc;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OidcDiscoveryService
{
    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string, jwks_uri?: string}
     */
    public function discover(string $issuer): array
    {
        $issuer = rtrim($issuer, '/');

        return Cache::remember(
            "oidc.discovery.{$issuer}",
            config('oidc.discovery_cache_ttl', 3600),
            function () use ($issuer) {
                $response = Http::timeout(10)->get("{$issuer}/.well-known/openid-configuration");
                $response->throw();

                $document = $response->json();

                foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $required) {
                    if (empty($document[$required])) {
                        throw new \RuntimeException("OIDC discovery document from {$issuer} is missing \"{$required}\".");
                    }
                }

                return $document;
            }
        );
    }
}
