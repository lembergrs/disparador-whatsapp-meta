<?php

define('GOOGLE_TAG_MANAGER_ID', 'GTM-5BV2SLDR');
$partial = __DIR__ . '/../app/Views/partials/google_tag_manager.php';
function gtmEnabledAssert($condition, $message){ if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

$googleTagManagerSection = 'head'; ob_start(); require $partial; $head = ob_get_clean();
$googleTagManagerSection = 'body'; ob_start(); require $partial; $body = ob_get_clean();

gtmEnabledAssert(substr_count($head, 'googletagmanager.com/gtm.js') === 1, 'head deve conter um único loader GTM');
gtmEnabledAssert(substr_count($body, 'googletagmanager.com/ns.html') === 1 && substr_count($body, '<noscript>') === 1, 'body deve conter um único fallback noscript');
gtmEnabledAssert(strpos($head, "window.dataLayer = window.dataLayer || [];") !== false, 'dataLayer deve existir antes do bootstrap');
gtmEnabledAssert(strpos($head, 'window.Disparador.analytics') !== false && strpos($head, 'push: function(evento, dados)') !== false, 'biblioteca de analytics deve ser global');
gtmEnabledAssert(strpos($head, 'gtag(') === false, 'gtag.js não deve ser instalado diretamente');
gtmEnabledAssert(strpos($head . $body, 'GTM-5BV2SLDR') !== false, 'container configurado deve ser renderizado');

$roots = array_merge(
    glob(__DIR__ . '/../app/Views/auth/*.php'),
    [__DIR__ . '/../app/Views/blog/layout.php', __DIR__ . '/../app/Views/layouts/master.php'],
    glob(__DIR__ . '/../app/Views/site/*.php')
);
foreach($roots as $file){
    $source = file_get_contents($file);
    if(stripos($source, '<!doctype html') === false) continue;
    gtmEnabledAssert(substr_count($source, "googleTagManagerSection = 'head'") === 1, basename($file) . ' deve renderizar um head GTM');
    gtmEnabledAssert(substr_count($source, "googleTagManagerSection = 'body'") === 1, basename($file) . ' deve renderizar um body GTM');
}

echo "GoogleTagManagerEnabledTest OK\n";
