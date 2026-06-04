<?php

require '../../config/config.php';
require '../../vendor/autoload.php';

$input =
    file_get_contents(
        'php://input'
    );

file_put_contents(

    __DIR__ . '/meta.log',

    date('Y-m-d H:i:s')
    . "\n"
    . $input
    . "\n\n",

    FILE_APPEND

);

http_response_code(200);

echo 'EVENT_RECEIVED';