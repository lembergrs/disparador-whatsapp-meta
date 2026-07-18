<?php

return [
    'host' => defined('MAIL_HOST') ? MAIL_HOST : '',
    'port' => defined('MAIL_PORT') ? (int) MAIL_PORT : 587,
    'username' => defined('MAIL_USERNAME') ? MAIL_USERNAME : '',
    'password' => defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '',
    'encryption' => defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls',
    'from_address' => defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : '',
    'from_name' => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Disparador.net',
    'reply_to_address' => defined('MAIL_REPLY_TO_ADDRESS') ? MAIL_REPLY_TO_ADDRESS : '',
    'reply_to_name' => defined('MAIL_REPLY_TO_NAME') ? MAIL_REPLY_TO_NAME : 'Suporte Disparador.net',
    'timeout' => defined('MAIL_TIMEOUT') ? (int) MAIL_TIMEOUT : 10,
];
