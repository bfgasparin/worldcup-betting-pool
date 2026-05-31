<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator emails
    |--------------------------------------------------------------------------
    |
    | Users whose email appears in this list are application administrators
    | (see App\Models\User::isAdmin) across every tournament — e.g. they may
    | advance a tournament through its lifecycle. Set ADMIN_EMAILS to a
    | comma-separated list of emails.
    |
    */

    'emails' => array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_EMAILS', '')),
    )),

];
