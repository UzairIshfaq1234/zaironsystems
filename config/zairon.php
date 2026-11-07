<?php

return [

    /*
    | Where new project enquiries are emailed. Leave empty to only store them in the database.
    */
    'inbox' => env('ZAIRON_INBOX'),

    /*
    | The company's local timezone, used where a human reads the time, e.g. invoice numbers
    | (ZRON-YYYYMMDDHHMMSSmmm). The app itself stores times in UTC.
    */
    'timezone' => env('ZAIRON_TIMEZONE', 'Asia/Karachi'),

    /*
    | Social sharing image (1200×630) inside /public. Only advertised when the file exists.
    */
    'og_image' => 'og-image.png',

    /*
    | Extra Content-Security-Policy sources, space separated. Example for a Google Maps embed:
    | CSP_FRAME_SRC="https://www.google.com"
    */
    'csp' => [
        'frame-src' => array_filter(explode(' ', (string) env('CSP_FRAME_SRC', ''))),
    ],

];
