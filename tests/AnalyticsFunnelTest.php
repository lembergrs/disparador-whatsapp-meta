<?php

session_start();
require_once __DIR__ . '/../app/Services/AnalyticsService.php';
require_once __DIR__ . '/../app/Services/ArtigoConteudoService.php';

use Services\AnalyticsService;
use Services\ArtigoConteudoService;

function analyticsAssert($condition, $message){ if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

AnalyticsService::registrar('sign_up', ['method'=>'public_form', 'account_type'=>'client', 'email'=>'privado@example.test']);
AnalyticsService::registrar('sign_up', ['method'=>'public_form', 'account_type'=>'client', 'email'=>'outro@example.test']);
AnalyticsService::registrar('login', ['method'=>'password', 'telefone'=>'5511999999999']);
AnalyticsService::registrar('evento_nao_permitido', ['valor'=>'x']);
$fila = AnalyticsService::consumir();
analyticsAssert(count($fila) === 2, 'fila deve remover duplicidade e rejeitar eventos desconhecidos');
analyticsAssert($fila[0] === ['evento'=>'sign_up','dados'=>['method'=>'public_form','account_type'=>'client']], 'sign_up deve conter apenas parâmetros permitidos sem PII');
analyticsAssert($fila[1] === ['evento'=>'login','dados'=>['method'=>'password']], 'login deve conter apenas método sem PII');
analyticsAssert(AnalyticsService::consumir() === [], 'eventos backend devem ser consumidos uma única vez');
$purchase = AnalyticsService::prepararPurchase([
    'transaction_id'=>'cobranca-123', 'value'=>'49.90', 'email'=>'privado@example.test',
    'item'=>['item_id'=>'pro', 'item_name'=>'Plano Pro', 'token'=>'segredo']
]);
analyticsAssert($purchase === ['evento'=>'purchase','dados'=>[
    'transaction_id'=>'cobranca-123','value'=>49.9,'currency'=>'BRL',
    'items'=>[['item_id'=>'pro','item_name'=>'Plano Pro','item_category'=>'subscription','price'=>49.9,'quantity'=>1]],
]], 'purchase deve usar apenas o schema permitido sem PII');

AnalyticsService::registrar('sign_up', ['method'=>'public_form', 'account_type'=>'client']);
$googleTagManagerSection = 'head';
ob_start(); require __DIR__ . '/../app/Views/partials/google_tag_manager.php'; $analyticsHead = ob_get_clean();
analyticsAssert(substr_count($analyticsHead, 'window.Disparador.analytics.push(') === 2 && strpos($analyticsHead, '"sign_up"') !== false && strpos($analyticsHead, '"method":"public_form"') !== false, 'partial deve entregar evento backend uma única vez pela biblioteca');

$root = dirname(__DIR__);
$site = file_get_contents($root . '/app/Controllers/SiteController.php');
$login = file_get_contents($root . '/app/Controllers/LoginController.php');
$cliente = file_get_contents($root . '/app/Models/Cliente.php');
$metaController = file_get_contents($root . '/app/Controllers/ConfiguracaoController.php');
$metaView = file_get_contents($root . '/app/Views/configuracao/meta.php');
$cadastro = file_get_contents($root . '/app/Views/site/cadastro.php');
$gtm = file_get_contents($root . '/app/Views/partials/google_tag_manager.php');
$whatsapp = file_get_contents($root . '/app/Views/site/partials/whatsapp_button.php');
$home = file_get_contents($root . '/app/Views/site/home.php');
$blogLayout = file_get_contents($root . '/app/Views/blog/layout.php');
$blogView = file_get_contents($root . '/app/Views/blog/artigo.php');
$campanhaController = file_get_contents($root . '/app/Controllers/CampanhaController.php');
$financeiro = file_get_contents($root . '/app/Services/FinanceiroWorkflowService.php');
$analyticsService = file_get_contents($root . '/app/Services/AnalyticsService.php');

analyticsAssert(strpos($site, "AnalyticsService::registrar('sign_up'") > strpos($site, '$db->commit();'), 'sign_up deve ocorrer somente depois do commit');
$loginEvent = strpos($login, "AnalyticsService::registrar('login'");
analyticsAssert($loginEvent !== false && $loginEvent > strpos($login, 'password_verify(') && $loginEvent < strpos($login, "'Usuário ou senha inválidos.'"), 'login deve existir apenas no ramo de autenticação bem-sucedida');
analyticsAssert(strpos($cliente, "AnalyticsService::registrar('start_trial'") > strpos($cliente, '$sql->rowCount() > 0'), 'start_trial deve depender da gravação inédita de CLI_DataLiberacao');
analyticsAssert(strpos($metaController, "AnalyticsService::registrar('connect_meta'") > strpos($metaController, 'atualizarStatusOperacionalEmbeddedSignup'), 'connect_meta deve ocorrer depois da persistência operacional');
analyticsAssert(strpos($metaController, 'if($atualizou &&') !== false, 'conclusão Meta deve exigir confirmação da atualização no banco');
analyticsAssert(strpos($metaController, "(\$conta['MTA_Status'] ?? '') !== 'conectado'") !== false, 'conclusão Meta deve impedir duplicidade por transição de estado');
analyticsAssert(strpos($metaController, "!empty(\$conta['MTA_WabaId'])") !== false && strpos($metaController, "!empty(\$conta['MTA_PhoneNumberId'])") !== false, 'conclusão Meta deve exigir WABA e número persistidos');
analyticsAssert(strpos($metaView, "window.Disparador.analytics.push('begin_meta_connection'") > strpos($metaView, ".then(function(configuracao)"), 'início Meta deve ocorrer após backend aceitar a inicialização');
analyticsAssert(substr_count($cadastro, "window.Disparador.analytics.push('sign_up_start'") === 1 && strpos($cadastro, 'inicioCadastroRastreado') !== false && substr_count($cadastro, 'removeEventListener') === 2, 'sign_up_start deve disparar somente na primeira interação');
analyticsAssert(strpos($whatsapp, 'data-analytics-event="click_whatsapp"') !== false && substr_count($gtm, "document.addEventListener('click'") === 1, 'WhatsApp deve usar um único listener delegado por clique');
analyticsAssert(substr_count($home . $blogLayout . $blogView, 'url=site/cadastro') === substr_count($home . $blogLayout . $blogView, 'data-analytics-event="select_trial"'), 'todos os CTAs de cadastro devem estar instrumentados');
analyticsAssert(strpos($home, "analytics.push('view_home'") !== false && strpos($home, "analytics.push('view_pricing'") !== false && strpos($home, 'IntersectionObserver') !== false, 'home e preços devem ter eventos contextuais sem duplicar page_view');
analyticsAssert(strpos($campanhaController, "AnalyticsService::registrar('first_campaign_created'") > strpos($campanhaController, 'atualizarTotalContatos'), 'primeira campanha deve ser registrada somente após criação completa');
analyticsAssert(strpos($financeiro, "status === 'pago'") !== false && strpos($financeiro, "AnalyticsService::registrar('purchase'") === false, 'purchase não pode ser emitido diretamente em pagamento pendente ou webhook sem sessão do cliente');
analyticsAssert(strpos($analyticsService, 'prepararPurchase') !== false && strpos($analyticsService, "'currency'=>'BRL'") !== false, 'payload seguro de purchase deve estar preparado para integração futura');
foreach(['view_home','view_pricing','select_trial','sign_up_start','sign_up','start_trial','connect_meta','first_campaign_created','purchase'] as $evento){
    analyticsAssert(strpos($home . $cadastro . $site . $cliente . $metaController . $campanhaController . $analyticsService, $evento) !== false, 'evento ausente: ' . $evento);
}
analyticsAssert(strpos($blogView, "'article_slug'") !== false && strpos($blogView, "'article_title'") !== false && strpos($blogView, "'article_category'") !== false && strpos($blogView, "'article_author'") !== false && strpos($blogView, "'article_reading_time'") !== false, 'artigo deve enviar todos os dados comportamentais requeridos');
analyticsAssert(ArtigoConteudoService::tempoLeitura('<p>' . implode(' ', array_fill(0, 221, 'palavra')) . '</p>') === 2, 'tempo real de leitura deve ser calculado sem consulta adicional');

$diretos = [];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app')) as $file){
    if(!$file->isFile() || $file->getExtension() !== 'php') continue;
    $source = file_get_contents($file->getPathname());
    if(strpos($source, 'window.dataLayer.push(') !== false && basename($file->getPathname()) !== 'google_tag_manager.php') $diretos[] = $file->getPathname();
    if(strpos($source, 'gtag(') !== false) $diretos[] = $file->getPathname();
}
analyticsAssert($diretos === [], 'eventos não podem acessar dataLayer ou gtag fora da biblioteca');

analyticsAssert(strpos($blogView . $cadastro . $metaView . $whatsapp . $home, 'CLI_ID') === false, 'instrumentação frontend não deve expor IDs internos');

echo "AnalyticsFunnelTest OK\n";
