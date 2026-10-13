<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Item Image Processing
    |--------------------------------------------------------------------------
    |
    | "sync" processes the compressed R2 upload during the request. This is
    | simpler for local/single-user setups. "queue" stores the pending upload
    | and requires a running Laravel queue worker.
    |
    */
    'processing' => env('ITEM_IMAGE_PROCESSING', 'sync'),
];
