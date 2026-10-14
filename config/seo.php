<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search Engine Metadata
    |--------------------------------------------------------------------------
    |
    | The brand shown in titles and structured data, and the single origin
    | used for canonical URLs so www and non-www hosts point to one address.
    |
    */

    'site_name' => env('SEO_SITE_NAME', 'Tarombo Connection'),

    'canonical_url' => rtrim((string) env('SEO_CANONICAL_URL', 'https://taromboconnection.com'), '/'),

    'default_description' => 'Jelajahi tarombo dan silsilah marga Batak melalui Tarombo Connection. Temukan hubungan kekerabatan dan kenali asal-usul keluarga Batak.',

    'default_image' => '/icons/icon-512.png',

];
