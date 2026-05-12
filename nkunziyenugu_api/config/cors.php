<?php
// Production deploys SPA and API on the same origin (nkunziyenungu.co.za),
// so CORS isn't actually exercised in normal traffic. We still lock the
// allowlist down — defense-in-depth against a misconfigured proxy or a
// future split-domain deployment leaking permissive headers.
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        'https://nkunziyenungu.co.za',
        'https://www.nkunziyenungu.co.za',
        // Local dev — Vue CLI dev server origins. Only included when APP_ENV is local.
        env('APP_ENV') === 'local' ? 'http://localhost:8080' : null,
        env('APP_ENV') === 'local' ? 'http://192.168.101.161:8080' : null,
    ])),

    'allowed_headers' => ['*'],

    'supports_credentials' => false,
];
