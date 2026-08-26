<?php

return [
    'keys' => ['v1' => env('CREDENTIAL_ENCRYPTION_KEY_V1')],
    'reauth_minutes' => (int) env('CREDENTIAL_REAUTH_MINUTES', 5),
];
