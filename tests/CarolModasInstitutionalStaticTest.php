<?php

$assert = function ($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};
$root = dirname(__DIR__);
$page = $root . '/public/institucionais/carol-modas';
$assert(is_file($page . '/index.html'), 'Página HTML pública deve existir.');
$html = file_get_contents($page . '/index.html');
$text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
foreach (['Carol Modas', 'C. A. DA S. OLIVEIRA', '34.640.908/0001-81', '13.780.720-1', '6900',
    'Rua José Olavo Gonçalves, 190N', 'Centro', 'Juara – MT', '78575-000',
    '(66) 99965-3195', 'castelocarol@outlook.com', '@carolmodasjuara',
    'Segunda a sexta', '08h às 11h', '13h às 18h', 'Sábado', '12h às 20h',
    'Página institucional hospedada por Disparador.net.'] as $required) {
    $assert(str_contains($text, $required), 'Informação obrigatória ausente: ' . $required);
}
// Lista permitida: rejeita qualquer outro telefone, inclusive números pessoais desconhecidos.
preg_match_all('/(?<!\d)(?:\+?55[\s.-]*)?\(?\d{2}\)?[\s.-]*9?\d{4}[\s.-]*\d{4}(?!\d)/', $html, $phones);
$assert(count($phones[0]) >= 3, 'Telefone visível e links de contato devem existir.');
foreach ($phones[0] as $phone) {
    $assert(in_array(preg_replace('/\D/', '', $phone), ['66999653195', '5566999653195'], true), 'Telefone não autorizado.');
}
foreach (['tel:+5566999653195', 'https://wa.me/5566999653195', 'https://www.instagram.com/carolmodasjuara/'] as $link) {
    $assert(str_contains($html, 'href="' . $link . '"'), 'Link comercial incorreto.');
}
preg_match_all('/(?:src|href)="(assets\/[^"#]+)"/', $html, $assets);
$assert(count($assets[1]) === 4, 'CSS, favicon e duas imagens locais devem existir.');
foreach ($assets[1] as $asset) {
    $resolved = realpath($page . '/' . $asset);
    $assert($resolved && str_starts_with($resolved, realpath($page) . DIRECTORY_SEPARATOR), 'Asset deve estar isolado na pasta da empresa.');
}
foreach (['assets/img/logo-carol-modas.png', 'assets/img/fachada-carol-modas.png', 'assets/css/style.css'] as $asset) {
    $assert(in_array($asset, $assets[1], true), 'Asset obrigatório ausente.');
}
$assert(!preg_match('/<\?(?:php|=)?|<(?:script|form|iframe)\b|\b(?:session_start|require|include)\s*\(/i', $html), 'Página deve renderizar sem execução, backend ou MVC.');
$assert(!preg_match('/(?:src|href)="(?:\/|\.\.)/', $html), 'Recursos não devem depender da raiz da aplicação.');
$assert(!preg_match('/domingo|sunday|verificação da Meta|aprovação do WhatsApp|página temporária/i', $text), 'Conteúdo não fornecido ou referência à verificação.');
$assert(str_contains($html, '<link rel="canonical" href="https://carolmodas.disparador.net/">'), 'Canonical incorreto.');
foreach (['/.htaccess', '/public/.htaccess'] as $file) {
    $rules = file_get_contents($root . $file);
    $assert(strpos($rules, 'RewriteCond %{REQUEST_FILENAME} -f [OR]') < strpos($rules, 'RewriteRule ^(.*)$ index.php'), 'Arquivos estáticos devem preceder MVC.');
    $assert(str_contains($rules, 'RewriteCond %{REQUEST_FILENAME} -d'), 'Diretórios estáticos devem ser preservados.');
}
echo "OK: página Carol Modas, dados comerciais, assets locais e isolamento estático.\n";
