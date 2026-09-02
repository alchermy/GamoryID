<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal document versions
    |--------------------------------------------------------------------------
    |
    | Bump these (ISO date) whenever the Terms of Service or Privacy Policy
    | changes materially. The version a user accepted is stored on the user row
    | (users.terms_version). Keep in sync with public-web/src/features/legal/meta.ts.
    |
    */

    'terms_version' => env('LEGAL_TERMS_VERSION', '2026-09-03'),
    'privacy_version' => env('LEGAL_PRIVACY_VERSION', '2026-09-03'),
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-09-03'),

    /*
    |--------------------------------------------------------------------------
    | Service provider / data controller identity
    |--------------------------------------------------------------------------
    |
    | Shown in the Terms and Privacy Policy. Set LEGAL_CONTROLLER_EMAIL to a
    | real, monitored address before opening the service to the public.
    |
    */

    'controller_name' => env('LEGAL_CONTROLLER_NAME', 'Art Thanawat'),
    'controller_email' => env('LEGAL_CONTROLLER_EMAIL', '[ระบุอีเมลติดต่อ]'),
    'controller_address' => env('LEGAL_CONTROLLER_ADDRESS'),

];
