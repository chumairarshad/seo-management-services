<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ops maintenance token
    |--------------------------------------------------------------------------
    |
    | Used by GET /_ops/{action}?token=… for Hostinger deploys without SSH.
    | When empty/null, the entire ops surface is disabled (404).
    | Rotate after every first deploy and any suspected leak.
    |
    */

    'token' => env('OPS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Rate limit (requests per minute per IP)
    |--------------------------------------------------------------------------
    */

    'throttle' => (int) env('OPS_THROTTLE', 5),

];
