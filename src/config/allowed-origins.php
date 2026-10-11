<?php

/**
 * Allowed origins: the browser origins (scheme + host + port, no path) that may call this API.
 *
 * Two things depend on this list, so it must name EVERY origin that hosts your sign-in page:
 *   1. CORS (CorsMiddleware): an origin that is not listed gets no CORS headers.
 *   2. The builtin Google OAuth popup (framework 12+): the sign-in result (access and refresh
 *      tokens) is posted ONLY to a listed origin or to the API's own origin. A sign-in page
 *      on an unlisted origin cannot complete sign-in. Wildcards are ignored.
 *
 * Keep this file a plain `return [...]` of string literals (no getenv(), no concatenation):
 * the runtime and the `stone` CLI both read it as data, and anything else makes them fall
 * back to the ALLOWED_ORIGINS environment variable with a warning. If ALLOWED_ORIGINS is set
 * (comma-separated, in .env or the process environment) it overrides this list.
 *
 * Replace the development entry before you deploy, then verify:
 *   php stone auth:check-origins      (lists the origins in effect; exits 1 if there are none)
 */
return [
    'http://localhost:4200',
    // 'https://app.example.com',
];
