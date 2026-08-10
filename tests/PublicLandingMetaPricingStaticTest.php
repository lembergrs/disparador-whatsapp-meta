<?php

$root = dirname(__DIR__);
$home = file_get_contents($root . '/app/Views/site/home.php');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($home, 'Tarifas da WhatsApp Business Platform cobradas pela Meta não estão incluídas na mensalidade') !== false, 'independência entre franquia e tarifas da Meta ausente');
$assert(strpos($home, '1º de outubro de 2026') !== false, 'data da atualização da Meta ausente');
$assert(strpos($home, 'mensagens de Serviço') !== false, 'mensagens de Serviço ausentes');
$assert(strpos($home, 'templates de Utilidade') !== false, 'templates de Utilidade ausentes');
$assert(!preg_match('/R\$\s*0[,.]0(?:3(?:5)?|5)\b/', $home), 'tarifa fixa da Meta encontrada');
$assert(substr_count($home, '<h1') === 1, 'Home deve manter somente um H1');

foreach(['header', 'hero', 'pricing', 'final_cta'] as $localizacao){
    $assert(strpos($home, 'data-analytics-location="' . $localizacao . '"') !== false, 'CTA principal ausente: ' . $localizacao);
}

echo "Public landing Meta pricing static checks passed\n";
