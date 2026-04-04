<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Container Bootstrap
    |--------------------------------------------------------------------------
    |
    | These values support image-based deployments where the application needs
    | to prepare its own runtime on first boot. The bundled Docker image uses
    | them to create the SQLite database and optionally provision an admin.
    |
    */

    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'Administrator'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],

];
