<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$htaccess = file_get_contents(dirname(__DIR__) . '/.htaccess');

$regraUploads = 'RewriteRule ^uploads/(.*)$ public/uploads/$1 [L]';
$regraMvc = 'RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]';

$posUploads = strpos($htaccess, $regraUploads);
$posMvc = strpos($htaccess, $regraMvc);

$assert($posUploads !== false, 'A raiz deve encaminhar /uploads para public/uploads.');
$assert($posMvc !== false, 'A regra principal do MVC deve continuar presente.');
$assert($posUploads < $posMvc, 'A regra de uploads deve ser aplicada antes do fallback do MVC.');

fwrite(STDOUT, "OK: roteamento público de uploads configurado antes do MVC.\n");
