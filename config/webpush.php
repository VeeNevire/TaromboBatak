<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Keys that identify this server to browser push services. Generate a pair
    | once with `php artisan webpush:vapid` and keep them stable: changing the
    | keys invalidates every existing subscription.
    |
    */

    'public_key' => env('VAPID_PUBLIC_KEY'),

    'private_key' => env('VAPID_PRIVATE_KEY'),

    // Push services only accept a URL or a mailto: address; a bare email
    // address is turned into mailto: so sending does not fail.
    'subject' => (function (): ?string {
        $subject = env('VAPID_SUBJECT', env('APP_URL'));

        return is_string($subject) && filter_var($subject, FILTER_VALIDATE_EMAIL)
            ? 'mailto:'.$subject
            : $subject;
    })(),

];
