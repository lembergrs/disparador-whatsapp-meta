<?php
$root = dirname(__DIR__);
$home = file_get_contents($root . '/app/Views/site/home.php');
$limites = file_get_contents($root . '/app/Views/site/limites_whatsapp.php');
$precos = file_get_contents($root . '/app/Views/site/precos_whatsapp_meta.php');
$router = file_get_contents($root . '/app/Core/Router.php');
$sitemap = file_get_contents($root . '/sitemap.xml');
$assert = function($ok,$message){ if(!$ok){ fwrite(STDERR,"FAIL: {$message}\n"); exit(1); } };
$assert(substr_count(strtolower($home), '<h1') === 1, 'home deve manter um H1');
$assert(strpos($home, 'Transforme seu WhatsApp em uma plataforma de') !== false, 'H1 comercial foi preservado');
$assert(stripos($home, 'Sem WhatsApp Web') === false && stripos($home, 'Sem celular conectado') === false, 'promessas incompatíveis foram removidas');
$assert(strpos($home, '/whatsapp-business') !== false && strpos($home, 'data-analytics-event="whatsapp_business"') !== false, 'CTA WhatsApp Business está instrumentado');
$assert(strpos($home, 'Feito para empresas que usam o WhatsApp todos os dias') !== false, 'seção Para quem é existe');
$assert(strpos($home, 'Do WhatsApp da empresa para uma operação profissional') !== false, 'comparação existe');
$assert(strpos($home, "partials/planos.php") < strpos($home, 'id="programa-indicacao"'), 'planos vêm antes da indicação');
$assert(strpos($home, 'Mais escolhido') === false, 'não usa alegação sem dados');
$assert(strpos($home, 'id="depoimentos"') !== false && strpos($home, "if(!empty(\$depoimentosPublicados))") !== false, 'prova social é condicional');
$assert(substr_count(strtolower($limites), '<h1') === 1 && substr_count(strtolower($precos), '<h1') === 1, 'páginas SEO têm um H1');
foreach(['canonical','og:title','meta name="description"'] as $meta){ $assert(stripos($limites,$meta)!==false && stripos($precos,$meta)!==false, "SEO inclui {$meta}"); }
$assert(strpos($router, "'limites-whatsapp'") !== false && strpos($router, "'precos-whatsapp-meta'") !== false, 'rotas amigáveis existem');
$assert(strpos($sitemap, '/limites-whatsapp') !== false && strpos($sitemap, '/precos-whatsapp-meta') !== false, 'sitemap inclui páginas');
$assert(strpos($home, 'traditional/coexistence') === false && strpos($home, 'name="onboarding_type"') === false, 'landing não cria seletor técnico');
echo "Landing conversion and SEO static tests passed\n";
