<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention windows
    |--------------------------------------------------------------------------
    |
    | DataRetentionLifecycle runs daily and applies these. Set a value to 0 to
    | disable that particular sweep.
    |
    */

    // Anonymize a customer's contact details (phone / LINE / Facebook / notes)
    // this many months after their last sale or profile update.
    'customer_contact_months' => (int) env('PRIVACY_CUSTOMER_CONTACT_MONTHS', 24),

    // Keep activity_logs rows for this many months, then delete.
    'activity_log_months' => (int) env('PRIVACY_ACTIVITY_LOG_MONTHS', 24),

    // Keep finished import jobs and their per-row error rows for this many days.
    'import_job_days' => (int) env('PRIVACY_IMPORT_JOB_DAYS', 90),

    // Delete the stored slip image file (the payment record itself is kept) this
    // many days after the top-up was submitted.
    'slip_file_days' => (int) env('PRIVACY_SLIP_FILE_DAYS', 180),

];
