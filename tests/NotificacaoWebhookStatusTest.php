<?php
if(!extension_loaded('pdo_sqlite')){ echo "NotificacaoWebhookStatusTest SKIP: pdo_sqlite indisponível\n"; exit(0); }
require_once __DIR__ . '/../app/Services/MensagemStatusService.php';
require_once __DIR__ . '/../app/Services/CanalNotificacao.php';
require_once __DIR__ . '/../app/Services/EventoNotificacao.php';
require_once __DIR__ . '/../app/Services/NotificacaoFormatador.php';
require_once __DIR__ . '/../app/Services/NotificacaoStatusService.php';
require_once __DIR__ . '/../app/Models/Notificacao.php';
use Models\Notificacao;

function notificacaoWebhookAssert($condicao, $mensagem){ if(!$condicao){ fwrite(STDERR, "FAIL: {$mensagem}\n"); exit(1); } }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE notificacoes (NOT_ID INTEGER PRIMARY KEY, NOT_Canal TEXT, NOT_ProviderMessageId TEXT, NOT_Status TEXT, NOT_DataEnvio TEXT, NOT_DataEntrega TEXT, NOT_DataLeitura TEXT, NOT_DataErro TEXT, NOT_CodigoErro TEXT, NOT_Erro TEXT)");
$db->exec("INSERT INTO notificacoes VALUES (1,'whatsapp','wamid.1','pendente',NULL,NULL,NULL,NULL,NULL,NULL),(2,'whatsapp','wamid.2','enviada','2026-08-06 14:32:00',NULL,NULL,NULL,NULL,NULL),(3,'email','wamid.email','enviada',NULL,NULL,NULL,NULL,NULL,NULL)");
$repo = new Notificacao($db);

notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorWamid('wamid.1','sent','2026-08-06 14:32:00')['atualizada'], 'sent deve atualizar');
notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorWamid('wamid.1','delivered','2026-08-06 14:33:00')['atualizada'], 'delivered deve atualizar');
notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorWamid('wamid.1','read','2026-08-06 14:35:00')['atualizada'], 'read deve atualizar');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorWamid('wamid.1','delivered','2026-08-06 14:36:00')['atualizada'], 'evento fora de ordem não deve regredir');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorWamid('wamid.1','read','2026-08-06 14:35:00')['atualizada'], 'webhook repetido deve ser idempotente');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorWamid('wamid.inexistente','sent')['encontrada'], 'wamid desconhecido deve ser ignorado');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorWamid('','sent')['encontrada'], 'wamid vazio deve ser ignorado');
$linha = $db->query("SELECT * FROM notificacoes WHERE NOT_ID=1")->fetch(PDO::FETCH_ASSOC);
notificacaoWebhookAssert($linha['NOT_Status']==='lida' && $linha['NOT_DataEnvio'] && $linha['NOT_DataEntrega'] && $linha['NOT_DataLeitura'], 'progressão e datas devem ser preservadas');

notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorWamid('wamid.2','failed','2026-08-06 14:40:00',['codigo'=>'131026<script>','mensagem'=>'token=segredo payload=privado configuração ausente'])['atualizada'], 'failed deve atualizar');
$falha = $db->query("SELECT * FROM notificacoes WHERE NOT_ID=2")->fetch(PDO::FETCH_ASSOC);
notificacaoWebhookAssert($falha['NOT_Status']==='erro_definitivo' && $falha['NOT_CodigoErro']==='131026script' && strpos($falha['NOT_Erro'],'segredo')===false && strpos($falha['NOT_Erro'],'privado')===false && $falha['NOT_DataErro'], 'erro deve ser persistido sanitizado');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorWamid('wamid.email','read')['encontrada'], 'webhook Meta não deve alterar e-mail');

$db->exec("INSERT INTO notificacoes VALUES (4,'whatsapp','wamid.read-direto','enviada','2026-08-06 14:30:00',NULL,NULL,NULL,NULL,NULL)");
notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorWamid('wamid.read-direto','read','2026-08-06 14:35:00')['atualizada'], 'read direto deve atualizar');
$direta = $db->query("SELECT * FROM notificacoes WHERE NOT_ID=4")->fetch(PDO::FETCH_ASSOC);
notificacaoWebhookAssert($direta['NOT_Status']==='lida' && $direta['NOT_DataEntrega']==='2026-08-06 14:35:00' && $direta['NOT_DataLeitura']==='2026-08-06 14:35:00' && $direta['NOT_DataEnvio']==='2026-08-06 14:30:00', 'read deve implicar entrega sem sobrescrever envio');

$fonte = file_get_contents(__DIR__ . '/../app/Models/Notificacao.php');
notificacaoWebhookAssert(strpos($fonte, "NOT_ProviderMessageId=?")!==false && strpos($fonte, 'NOT_ProviderMessageId LIKE')===false, 'associação deve usar igualdade exata pelo wamid');
notificacaoWebhookAssert(strpos($fonte, 'NOT_Destino=?')===false, 'associação não pode usar telefone');

echo "NotificacaoWebhookStatusTest OK\n";
