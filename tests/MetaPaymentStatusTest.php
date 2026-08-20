<?php
function paymentAssert($condition,$message){ if(!$condition){ fwrite(STDERR,"FAIL: {$message}\n"); exit(1); } }

$controller=file_get_contents(__DIR__.'/../app/Controllers/ConfiguracaoController.php');
$model=file_get_contents(__DIR__.'/../app/Models/MetaConta.php');
$migration=file_get_contents(__DIR__.'/../database/migrations/20260820_add_meta_payment_status.sql');
$view=file_get_contents(__DIR__.'/../app/Views/configuracao/meta.php');
$dashboard=file_get_contents(__DIR__.'/../app/Views/dashboard/index.php');
$dashboardController=file_get_contents(__DIR__.'/../app/Controllers/DashboardController.php');
$admin=file_get_contents(__DIR__.'/../app/Views/meta_contas/index.php');
$doc=file_get_contents(__DIR__.'/../docs/meta-payment-status.md');
$all=$controller.$model.$migration.$view.$dashboard.$dashboardController.$admin.$doc;

paymentAssert(strpos($controller,'if(!$contaExistenteId)')!==false&&strpos($controller,'marcarPagamentoMetaPendenteOnboarding($contaId,$clienteId)')!==false,'somente conta nova do onboarding deve entrar como pendente');
paymentAssert(strpos($model,"MTA_PagamentoMetaStatus<>'confirmado_cliente'")!==false,'reconexão não pode remover confirmação do cliente');
paymentAssert(strpos($controller,'public function confirmarPagamentoMeta()')!==false&&strpos($controller,'Csrf::exigirPost()')!==false,'confirmação deve ser POST com CSRF');
paymentAssert(strpos($controller,'buscarPorCliente($contaId,$clienteId)')!==false,'conta deve pertencer ao cliente autenticado');
paymentAssert(strpos($model,"MTA_PagamentoMetaStatus='confirmado_cliente',MTA_PagamentoMetaConfirmadoEm=NOW()")!==false,'confirmação deve gravar estado e timestamp');
paymentAssert(substr_count($model,"AND CLI_ID=? AND MTA_Ativo='S'")>=2,'pendência e confirmação devem validar cliente e conta ativa');
paymentAssert(strpos($migration,'MTA_PagamentoMetaConfirmadoEm')!==false&&strpos($migration,'MTA_PagamentoMetaVerificadoEm')===false,'timestamp deve ter semântica declaratória');
paymentAssert(strpos($migration,'ADD COLUMN IF NOT EXISTS')!==false&&strpos($migration,'NULL')!==false,'migration deve ser reexecutável e nullable');
paymentAssert(strpos($view,'Pendente de confirmação')!==false&&strpos($view,'Ainda não confirmada')!==false&&strpos($view,'Confirmada pelo cliente')!==false,'configuração deve mostrar os três estados visuais');
paymentAssert(strpos($view,'Configurar na Meta')!==false&&strpos($view,'Já configurei')!==false&&strpos($view,'configuracao/confirmarPagamentoMeta')!==false,'pendência deve mostrar link e confirmação');
paymentAssert(strpos($view,'noopener noreferrer')!==false,'link oficial deve abrir de forma segura');
paymentAssert(strpos($dashboardController,'buscarPagamentoMetaPendentePorCliente($cliId)')!==false,'dashboard deve usar a consulta testável do Model');
paymentAssert(strpos($dashboard,'Ação necessária: confirme a configuração de pagamento da Meta.')!==false,'dashboard deve alertar sobre pendência');
paymentAssert(strpos($dashboard,'confirmado_cliente')===false,'dashboard não deve alertar conta confirmada');
paymentAssert(strpos($admin,'Pendente de confirmação')!==false&&strpos($admin,'Confirmada pelo cliente')!==false&&strpos($admin,'Ainda não confirmada')!==false,'admin deve mostrar apenas estados declaratórios');
$campoFinanceiro='primary_'.'funding_id';
$diagnosticoHttp='graph_http_'.'status'; $diagnosticoCodigo='graph_error_'.'code';
$creditSharing='whatsapp_credit_'.'sharing_and_attach'; $extendedCredits='extended'.'credits';
paymentAssert(strpos($all,$campoFinanceiro)===false,'nenhuma consulta ou referência funcional ao campo financeiro deve permanecer');
paymentAssert(strpos($all,$diagnosticoHttp)===false&&strpos($all,$diagnosticoCodigo)===false,'instrumentação temporária deve ser removida');
paymentAssert(stripos($all,$creditSharing)===false&&stripos($all,$extendedCredits)===false,'credit sharing deve permanecer ausente');
paymentAssert(strpos($doc,'não valida tecnicamente')!==false&&strpos($doc,'não pretende adotar o modelo BSP')!==false,'documentação deve registrar a estratégia declaratória');

require_once __DIR__.'/../config/env.php';
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../app/Core/Database.php';
require_once __DIR__.'/../app/Models/MetaConta.php';

$db=\Core\Database::getInstance();
$db->exec("CREATE TEMPORARY TABLE meta_contas (
    MTA_ID INT PRIMARY KEY, CLI_ID INT NOT NULL, MTA_Ativo CHAR(1) NOT NULL,
    MTA_Status VARCHAR(30) NULL, MTA_PagamentoMetaStatus VARCHAR(30) NULL,
    MTA_PagamentoMetaConfirmadoEm DATETIME NULL, MTA_Token VARCHAR(255) NULL,
    MTA_WabaId VARCHAR(100) NULL, MTA_PhoneNumberId VARCHAR(100) NULL, MTA_Nome VARCHAR(100) NULL
)");
$meta=new \Models\MetaConta($db);
$inserir=$db->prepare("INSERT INTO meta_contas (MTA_ID,CLI_ID,MTA_Ativo,MTA_Status,MTA_PagamentoMetaStatus,MTA_PagamentoMetaConfirmadoEm,MTA_Token,MTA_WabaId,MTA_PhoneNumberId,MTA_Nome) VALUES (?,?,?,?,?,?,?,?,?,?)");
$add=function($id,$cliente,$ativo,$operacional,$pagamento=null,$confirmadoEm=null) use($inserir){
    $inserir->execute([$id,$cliente,$ativo,$operacional,$pagamento,$confirmadoEm,'token-imutavel','waba-imutavel','phone-imutavel','Conta imutável']);
};
$buscar=function($id) use($db){ $s=$db->prepare('SELECT * FROM meta_contas WHERE MTA_ID=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC); };

// Nova conta: pendência, confirmação, timestamp e preservação dos demais campos.
$add(1,10,'S','conectado');
paymentAssert($meta->marcarPagamentoMetaPendenteOnboarding(1,10)===true,'nova conta deve ser marcada como pendente');
paymentAssert($buscar(1)['MTA_PagamentoMetaStatus']==='pendente_confirmacao'&&$buscar(1)['MTA_PagamentoMetaConfirmadoEm']===null,'pendência não pode ter timestamp de confirmação');
$antes=$buscar(1);
paymentAssert($meta->confirmarPagamentoMetaPorCliente(1,10)===true,'primeira confirmação deve ter sucesso');
$depois=$buscar(1);
paymentAssert($depois['MTA_PagamentoMetaStatus']==='confirmado_cliente'&&!empty($depois['MTA_PagamentoMetaConfirmadoEm']),'confirmação deve gravar estado e timestamp');
foreach(['MTA_Token','MTA_WabaId','MTA_PhoneNumberId','MTA_Status','MTA_Nome'] as $campo) paymentAssert($depois[$campo]===$antes[$campo],"confirmação não pode alterar {$campo}");

// Mesmo segundo: força valores idênticos e comprova sucesso independente de rowCount.
$db->exec("SET timestamp=UNIX_TIMESTAMP('2026-08-20 12:34:56')");
$db->exec("UPDATE meta_contas SET MTA_PagamentoMetaStatus='confirmado_cliente',MTA_PagamentoMetaConfirmadoEm=NOW() WHERE MTA_ID=1");
$mesmoSegundo=$buscar(1)['MTA_PagamentoMetaConfirmadoEm'];
$controleRowCount=$db->prepare("UPDATE meta_contas SET MTA_PagamentoMetaStatus='confirmado_cliente',MTA_PagamentoMetaConfirmadoEm=NOW() WHERE MTA_ID=? AND CLI_ID=? AND MTA_Ativo='S'");
$controleRowCount->execute([1,10]);
paymentAssert($controleRowCount->rowCount()===0,'fixture deve reproduzir rowCount zero no mesmo segundo');
paymentAssert($meta->confirmarPagamentoMetaPorCliente(1,10)===true,'segunda confirmação no mesmo segundo deve ter sucesso');
paymentAssert($buscar(1)['MTA_PagamentoMetaStatus']==='confirmado_cliente'&&$buscar(1)['MTA_PagamentoMetaConfirmadoEm']===$mesmoSegundo,'segunda confirmação deve permanecer consistente');
$db->exec('SET timestamp=DEFAULT');

// Autorização por cliente e conta ativa.
$add(2,20,'S','conectado','pendente_confirmacao');
paymentAssert($meta->confirmarPagamentoMetaPorCliente(2,99)===false&&$buscar(2)['MTA_PagamentoMetaStatus']==='pendente_confirmacao','outro cliente deve ser negado');
$add(3,30,'N','conectado','pendente_confirmacao');
paymentAssert($meta->confirmarPagamentoMetaPorCliente(3,30)===false&&$buscar(3)['MTA_PagamentoMetaStatus']==='pendente_confirmacao','conta inativa deve ser negada');

// Reconexão/model responsável pela pendência preserva confirmação e timestamp.
$timestampAntigo='2026-08-01 12:00:00'; $add(4,40,'S','conectado','confirmado_cliente',$timestampAntigo);
paymentAssert($meta->marcarPagamentoMetaPendenteOnboarding(4,40)===false,'conta confirmada não deve voltar a pendente');
paymentAssert($buscar(4)['MTA_PagamentoMetaStatus']==='confirmado_cliente'&&$buscar(4)['MTA_PagamentoMetaConfirmadoEm']===$timestampAntigo,'reconexão deve preservar confirmação e timestamp');

// Sete cenários reais da consulta usada pelo Dashboard.
$db->exec('DELETE FROM meta_contas'); $add(10,50,'S','conectado','pendente_confirmacao');
paymentAssert($meta->buscarPagamentoMetaPendentePorCliente(50)!==null,'Dashboard: pendente conectada deve alertar');
$db->exec('DELETE FROM meta_contas'); $add(11,50,'S','conectado',null);
paymentAssert($meta->buscarPagamentoMetaPendentePorCliente(50)!==null,'Dashboard: NULL conectada deve alertar');
$db->exec('DELETE FROM meta_contas'); $add(12,50,'S','conectado','confirmado_cliente',$timestampAntigo);
paymentAssert($meta->buscarPagamentoMetaPendentePorCliente(50)===null,'Dashboard: confirmada não deve alertar');
$db->exec('DELETE FROM meta_contas'); $add(13,50,'N','conectado',null);
paymentAssert($meta->buscarPagamentoMetaPendentePorCliente(50)===null,'Dashboard: NULL inativa não deve alertar');
$db->exec('DELETE FROM meta_contas'); $add(14,50,'S','desconectado',null);
paymentAssert($meta->buscarPagamentoMetaPendentePorCliente(50)===null,'Dashboard: NULL desconectada não deve alertar');
$db->exec('DELETE FROM meta_contas'); $add(15,50,'S','conectado','confirmado_cliente',$timestampAntigo); $add(16,50,'S','conectado','pendente_confirmacao');
paymentAssert($meta->buscarPagamentoMetaPendentePorCliente(50)!==null,'Dashboard: uma pendente entre múltiplas deve alertar');
$db->exec('DELETE FROM meta_contas'); $add(17,50,'S','conectado','confirmado_cliente',$timestampAntigo); $add(18,50,'S','conectado','confirmado_cliente',$timestampAntigo);
paymentAssert($meta->buscarPagamentoMetaPendentePorCliente(50)===null,'Dashboard: todas confirmadas não devem alertar');

$db->exec('DROP TEMPORARY TABLE meta_contas');

echo "MetaPaymentStatusTest OK\n";
