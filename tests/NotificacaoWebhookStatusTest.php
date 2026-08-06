<?php
if(!extension_loaded('pdo_sqlite')){ echo "NotificacaoWebhookStatusTest SKIP: pdo_sqlite indisponível\n"; exit(0); }
require_once __DIR__ . '/../app/Services/MensagemStatusService.php';
require_once __DIR__ . '/../app/Models/Notificacao.php';
use Models\Notificacao;

function notificacaoWebhookAssert($condicao, $mensagem){ if(!$condicao){ fwrite(STDERR, "FAIL: {$mensagem}\n"); exit(1); } }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE notificacoes (NOT_ID INTEGER PRIMARY KEY, NOT_Canal TEXT, NOT_ProviderMessageId TEXT, NOT_Status TEXT, NOT_DataEnvio TEXT, NOT_DataEntrega TEXT, NOT_DataLeitura TEXT, NOT_DataErro TEXT, NOT_CodigoErro TEXT, NOT_Erro TEXT)");
$db->exec("INSERT INTO notificacoes VALUES (1,'whatsapp','wamid.1','pendente',NULL,NULL,NULL,NULL,NULL,NULL),(2,'whatsapp','wamid.2','pendente',NULL,NULL,NULL,NULL,NULL,NULL),(3,'email','wamid.email','enviada',NULL,NULL,NULL,NULL,NULL,NULL)");
$repo = new Notificacao($db);

notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorMessageId('wamid.1','sent','2026-08-06 14:32:00'), 'sent deve atualizar');
notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorMessageId('wamid.1','delivered','2026-08-06 14:33:00'), 'delivered deve atualizar');
notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorMessageId('wamid.1','read','2026-08-06 14:35:00'), 'read deve atualizar');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorMessageId('wamid.1','delivered','2026-08-06 14:36:00'), 'evento fora de ordem não deve regredir');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorMessageId('wamid.1','read','2026-08-06 14:35:00'), 'webhook repetido deve ser idempotente');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorMessageId('wamid.inexistente','sent'), 'wamid desconhecido deve ser ignorado');
$linha = $db->query("SELECT * FROM notificacoes WHERE NOT_ID=1")->fetch(PDO::FETCH_ASSOC);
notificacaoWebhookAssert($linha['NOT_Status']==='lida' && $linha['NOT_DataEnvio'] && $linha['NOT_DataEntrega'] && $linha['NOT_DataLeitura'], 'progressão e datas devem ser preservadas');

notificacaoWebhookAssert($repo->atualizarStatusWhatsAppPorMessageId('wamid.2','failed','2026-08-06 14:40:00',['codigo'=>'131026<script>','mensagem'=>'token=segredo configuração ausente']), 'failed deve atualizar');
$falha = $db->query("SELECT * FROM notificacoes WHERE NOT_ID=2")->fetch(PDO::FETCH_ASSOC);
notificacaoWebhookAssert($falha['NOT_Status']==='erro_definitivo' && $falha['NOT_CodigoErro']==='131026script' && strpos($falha['NOT_Erro'],'segredo')===false && $falha['NOT_DataErro'], 'erro deve ser persistido sanitizado');
notificacaoWebhookAssert(!$repo->atualizarStatusWhatsAppPorMessageId('wamid.email','read'), 'webhook Meta não deve alterar e-mail');

echo "NotificacaoWebhookStatusTest OK\n";
