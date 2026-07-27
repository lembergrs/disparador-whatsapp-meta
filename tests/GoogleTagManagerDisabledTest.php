<?php

define('GOOGLE_TAG_MANAGER_ID', '');
$partial = __DIR__ . '/../app/Views/partials/google_tag_manager.php';
function gtmDisabledAssert($condition, $message){ if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

$googleTagManagerSection = 'head'; ob_start(); require $partial; $head = ob_get_clean();
$googleTagManagerSection = 'body'; ob_start(); require $partial; $body = ob_get_clean();

gtmDisabledAssert(strpos($head . $body, 'googletagmanager.com') === false, 'ID vazio não deve carregar nem referenciar GTM');
gtmDisabledAssert(strpos($body, '<noscript>') === false, 'ID vazio não deve renderizar iframe');
gtmDisabledAssert(strpos($head, 'window.Disparador.analytics') !== false, 'camada futura deve permanecer segura quando GTM estiver desabilitado');
gtmDisabledAssert(strpos($head, 'window.dataLayer = window.dataLayer || [];') !== false, 'dataLayer vazio não deve lançar exceção');

echo "GoogleTagManagerDisabledTest OK\n";
