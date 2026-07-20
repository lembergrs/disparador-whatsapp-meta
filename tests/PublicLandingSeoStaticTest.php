<?php

$root = dirname(__DIR__);
$home = file_get_contents($root . '/app/Views/site/home.php');
$css = file_get_contents($root . '/public/assets/css/style.css');
$robots = file_get_contents($root . '/robots.txt');
$sitemap = file_get_contents($root . '/sitemap.xml');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($home, '<title>Disparador.net | Plataforma Oficial de WhatsApp Business da Meta</title>') !== false, 'title obrigatório ausente');
$assert(strpos($home, 'Envie campanhas, notificações e mensagens pela API Oficial do WhatsApp Business da Meta.') !== false, 'description obrigatória ausente');
$assert(strpos($home, '<link rel="canonical" href="https://disparador.net/">') !== false, 'canonical da Home ausente');
$assert(substr_count($home, '<h1') === 1, 'Home deve possuir somente um H1');
$assert(strpos($home, 'Envie mensagens pela API Oficial do WhatsApp Business da Meta') !== false, 'H1 comercial obrigatório ausente');
$assert(strpos($home, 'og:locale') !== false && strpos($home, 'twitter:card') !== false, 'metadados sociais ausentes');
$assert(substr_count($home, 'application/ld+json') === 3, 'schemas Organization, SoftwareApplication e FAQPage devem existir');
$assert(strpos($home, "'@type' => 'FAQPage'") !== false, 'FAQ schema ausente');
$assert(strpos($home, 'Faixas de envio da Meta') !== false && strpos($home, '<th scope="col">') !== false, 'tabela acessível de faixas ausente');
foreach(['250', '1.000', '10.000', '100.000', 'Ilimitado'] as $limite){
    $assert(strpos($home, '>' . $limite . '<') !== false, 'faixa ausente: ' . $limite);
}
$assert(strpos($home, 'precosMetaBrasil') === false, 'Home não deve publicar tarifas fixas da Meta');
$assert(strpos($home, 'até 7 dias ou 200 mensagens, o que ocorrer primeiro') !== false, 'regra real do teste grátis ausente');
$assert(strpos($css, '.site-meta-tiers-table') !== false, 'responsividade da tabela ausente');
$assert(strpos($robots, 'Sitemap: https://disparador.net/sitemap.xml') !== false, 'robots não referencia sitemap canônico');
$assert(strpos($robots, 'Disallow: /index.php?url=') === false, 'robots não pode bloquear rotas públicas por query string');
$assert(strpos($sitemap, '<loc>https://disparador.net/</loc>') !== false, 'Home ausente do sitemap');
$assert(strpos($sitemap, '/login') === false && strpos($sitemap, '/dashboard') === false, 'sitemap contém rota interna');

echo "Public landing SEO static checks passed\n";
