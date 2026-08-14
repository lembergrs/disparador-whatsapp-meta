<?php

function communicationAssert($condition, $message){ if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

$root = dirname(__DIR__);
$page = file_get_contents($root.'/app/Views/site/whatsapp_business.php');
$home = file_get_contents($root.'/app/Views/site/home.php');
$meta = file_get_contents($root.'/app/Views/configuracao/meta.php');
$router = file_get_contents($root.'/app/Core/Router.php');
$controller = file_get_contents($root.'/app/Controllers/SiteController.php');
$sitemap = file_get_contents($root.'/app/Controllers/SitemapController.php');

communicationAssert(substr_count(strtolower($page), '<h1') === 1, 'página pública possui exatamente um H1');
communicationAssert(preg_match('/<title>[^<]+<\/title>/', $page) === 1, 'página possui title específico');
communicationAssert(strpos($page, 'name="description"') !== false && strpos($page, 'rel="canonical" href="https://disparador.net/whatsapp-business"') !== false, 'página possui description e canonical');
communicationAssert(strpos($page, 'property="og:title"') !== false && strpos($page, 'FAQPage') !== false, 'página integra Open Graph e FAQ estruturado');
communicationAssert(strpos($page, '>Começar agora</a>') !== false && strpos($page, '/index.php?url=site/cadastro') !== false, 'CTA usa cadastro existente');
communicationAssert(strpos($page, 'inclusive leitura de QR Code quando aplicável') !== false, 'orientação admite QR Code quando aplicável');
communicationAssert(stripos($page, 'sem QR Code') === false, 'página não usa promessa sem QR Code');
communicationAssert(stripos($page, 'sincronização 100%') === false && stripos($page, 'todas as mensagens ficam') === false, 'página não promete sincronização integral');
communicationAssert(strpos($page, 'podem ser desconectados durante o processo') !== false && strpos($page, 'vinculados novamente') !== false, 'seção visível explica dispositivos vinculados');
communicationAssert(strpos($home, '/whatsapp-business') !== false && strpos($home, 'Conecte seu WhatsApp Business ao Disparador.net') !== false, 'landing contém seção e link para página educativa');
communicationAssert(strpos($meta, 'WhatsApp Web e WhatsApp Desktop') !== false && strpos($meta, 'Após finalizar, você poderá vinculá-los novamente.') !== false, 'tela de conexão contém aviso de dispositivos');
communicationAssert(strpos($meta, 'btnConectarWhatsAppCoexistence') === false && strpos($meta, 'data-onboarding-mode') === false, 'nenhum seletor technical foi reintroduzido');
communicationAssert(strpos($router, "\$url === 'whatsapp-business'") !== false && strpos($controller, 'function whatsappBusiness()') !== false, 'URL amigável usa roteamento existente');
communicationAssert(strpos($sitemap, 'https://disparador.net/whatsapp-business') !== false, 'sitemap dinâmico inclui nova página');

echo "WhatsApp Business public communication tests passed\n";
