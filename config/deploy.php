<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deploy hook
    |--------------------------------------------------------------------------
    |
    | Shared hosting gives CI no way to run artisan after uploading files, so
    | this optional endpoint does it over HTTP instead. It runs migrations and
    | rebuilds the caches, which is a lot of power to hang off a URL — so it
    | does not exist at all unless a token is set, and the token needs to be
    | long and random.
    |
    | If you have SSH or cPanel Terminal, prefer those and leave this unset.
    | See docs/DEPLOYMENT.md.
    |
    */

    'token' => env('DEPLOY_HOOK_TOKEN'),

];
