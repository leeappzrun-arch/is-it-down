<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Key Permission Resources
    |--------------------------------------------------------------------------
    |
    | Add every application area that should be addressable by an API key here.
    | Each resource automatically receives the standard read and write actions.
    | When new sections are introduced, add the resource slug and label so
    | the permission matrix stays aligned with the shipped feature set.
    |
    */

    'resources' => [
        'recipients' => 'Recipients',
        'users' => 'Users',
    ],

    'actions' => [
        'read' => 'Read',
        'write' => 'Write',
    ],
];
