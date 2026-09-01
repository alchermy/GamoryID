<?php

return [
    // Use "*" behind a dynamic tunnel such as ngrok. In production, prefer a
    // comma-separated list of known reverse proxy IP addresses when available.
    'proxies' => env('TRUSTED_PROXIES'),
];
