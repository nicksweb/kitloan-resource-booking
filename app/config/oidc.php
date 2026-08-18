<?php

return [

    'enabled' => env('OIDC_ENABLED', true),

    // Issuer base URL. The app fetches {issuer}/.well-known/openid-configuration
    // at runtime (cached) rather than hard-coding provider-specific endpoints,
    // so any standards-compliant OIDC provider (Entra ID, Keycloak, Okta, ...)
    // works without code changes.
    'issuer' => env('OIDC_ISSUER'),

    'client_id' => env('OIDC_CLIENT_ID'),
    'client_secret' => env('OIDC_CLIENT_SECRET'),
    'redirect_uri' => env('OIDC_REDIRECT_URI'),

    'scopes' => ['openid', 'profile', 'email'],

    // Comma-separated email domains allowed to authenticate. Empty = unrestricted.
    'allowed_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OIDC_ALLOWED_DOMAINS', ''))
    ))),

    // How long the fetched discovery document is cached for.
    'discovery_cache_ttl' => 3600,
];
