<?php

declare(strict_types=1);

return [

    'security' => [
        'csp_report_only' => filter_var(env('ANTIBODY_CSP_REPORT_ONLY', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
        'hsts_max_age' => (int) env('ANTIBODY_HSTS_MAX_AGE', 15552000),
    ],

];
