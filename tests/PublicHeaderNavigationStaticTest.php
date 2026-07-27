<?php

$root = dirname(__DIR__);
$home = file_get_contents($root . '/app/Views/site/home.php');
$css = file_get_contents($root . '/public/assets/css/style.css');

$assert = function($condition, $message){
    if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$assert(substr_count($home, 'class="nav-item dropdown"') === 2, 'header deve possuir exatamente dois dropdowns');
$assert(strpos($home, 'id="menuProduto"') !== false && strpos($home, 'aria-labelledby="menuProduto"') !== false, 'dropdown Produto deve usar marcação acessível do Bootstrap');
$assert(strpos($home, 'id="menuRecursos"') !== false && strpos($home, 'aria-labelledby="menuRecursos"') !== false, 'dropdown Recursos deve usar marcação acessível do Bootstrap');
foreach(['Como funciona', 'Campanhas pelo WhatsApp', 'Gestão de contatos', 'Atendimento e Conversas', 'API Oficial do WhatsApp', 'Faixas da Meta', 'FAQ'] as $item){
    $assert(strpos($home, '>' . $item . '</a>') !== false, 'item de navegação ausente: ' . $item);
}
$blogPos = strpos($home, 'href="<?= BASE_URL; ?>/blog"');
$produtoFim = strpos($home, '</div>', strpos($home, 'id="menuProduto"'));
$recursosFim = strpos($home, '</div>', strpos($home, 'id="menuRecursos"'));
$assert($blogPos !== false && $blogPos > $produtoFim && $blogPos > $recursosFim, 'Blog deve permanecer no primeiro nível');
$assert(preg_match('/>\s*Começar teste grátis\s*<\/a>/', $home) === 1, 'CTA principal deve preservar exatamente o texto');
$assert(strpos($home, 'data-toggle="collapse"') !== false && strpos($home, 'navbar-toggler') !== false, 'menu mobile deve preservar o hambúrguer');
$assert(strpos($home, 'bootstrap.bundle.min.js') !== false, 'Bootstrap bundle deve oferecer dropdown e fechamento externo');
$assert(strpos($css, 'white-space: nowrap') !== false && strpos($css, '.site-nav-button') !== false, 'header deve impedir quebras e alinhar botões');
$assert(strpos($css, '@keyframes site-dropdown-enter') !== false, 'dropdown deve possuir animação discreta');
$assert(strpos($css, '.site-navbar a:focus-visible') !== false, 'header deve preservar foco visível');

echo "Public header navigation static checks passed\n";
