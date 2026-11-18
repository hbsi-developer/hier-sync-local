<?php

return [

    /*
    | Push of attendance logs to Hier Payroll (see App\Services\LogPusher).
    | The password is never stored by Laravel: the Electron shell decrypts it (safeStorage)
    | and hands it to the artisan process through this environment variable only.
    */
    'password' => env('HIER_PUSH_PASSWORD'),

    'batch_size' => (int) env('HIER_PUSH_BATCH', 500),

    'max_batches_per_run' => 20,

    'timeout' => 30,

    'connect_timeout' => 10,

    'path' => '/api/hier-sync/attendance-logs',
];
