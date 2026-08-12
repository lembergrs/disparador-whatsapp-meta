<?php

function registerAssert($cond, $msg){
    if(!$cond){
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ConfiguracaoController.php');
$view = file_get_contents($root . '/app/Views/configuracao/meta.php');
$metaService = file_get_contents($root . '/app/Services/MetaService.php');
$workerValidator = file_get_contents($root . '/app/Services/WorkerOperationalValidatorService.php');
$migration = file_get_contents($root . '/database/migrations/20260717_expand_meta_status_for_register.sql');
$flow = file_get_contents($root . '/app/Services/EmbeddedSignupFlowService.php');

registerAssert(strpos($controller, "'pendente_registro'") !== false, 'Embedded Signup define pendente_registro');
registerAssert(
    strpos($controller, ": 'pendente_registro';") !== false
    && strpos($controller, "'status' => \$statusConexao") !== false,
    'persistência traditional continua usando pendente_registro'
);
registerAssert(strpos($controller, "Número vinculado com sucesso. Falta concluir o registro") !== false, 'mensagem não declara conexão concluída antes do register');
registerAssert(strpos($controller, 'pin_confirmacao') !== false, 'endpoint valida confirmação de PIN');
registerAssert(strpos($controller, 'registrarPhoneNumberMeta($conta[\'MTA_PhoneNumberId\'], $pin, $conta[\'MTA_Token\'])') !== false, 'register usa phone_number_id e token do banco');
registerAssert(strpos($controller, 'success') === false || strpos($flow, "'messaging_product' => 'whatsapp'") !== false, 'payload de register contém messaging_product whatsapp');
registerAssert(strpos($flow, "'pin' => $" . "pin") !== false, 'PIN é enviado somente ao service da Meta');
registerAssert(strpos($controller, "'conectado'") !== false && strpos($controller, 'iniciarTrialSePendente($clienteId)') !== false, 'sucesso marca conectado e inicia trial');
registerAssert(strpos($controller, "'erro_registro'") !== false, 'erro de register marca erro_registro');
registerAssert(strpos($controller, 'mensagemAmigavelErroMeta') !== false, 'erros Meta são mapeados para mensagens amigáveis');
registerAssert(strpos($controller, '133010') !== false && strpos($controller, 'Unsupported post request') !== false, 'mapeia 133010 e code 100/subcode 33');
registerAssert(strpos($controller, 'MTA_Token') !== false && strpos($controller, '$_POST[\'token\']') === false, 'token não vem do frontend');
registerAssert(strpos($controller, 'MTA_PhoneNumberId') !== false && strpos($controller, '$_POST[\'phone_number_id\']') === false, 'phone_number_id não vem do frontend');
registerAssert(strpos($view, 'name="pin_confirmacao"') !== false, 'UI confirma PIN');
registerAssert(strpos($view, 'pin-registro-whatsapp') !== false && strpos($view, 'substring(0, 6)') !== false, 'UI limita PIN a 6 dígitos numéricos');
registerAssert(strpos($view, 'Concluir conexão') !== false, 'UI exibe etapa de concluir conexão');
registerAssert(strpos($view, 'pendente_registro') !== false, 'retomada exibe registro pendente');
registerAssert(strpos($metaService, 'validarContaProntaParaEnvio') !== false && strpos($metaService, "status !== 'conectado'") !== false, 'MetaService bloqueia envios não conectados');
registerAssert(strpos($metaService, 'aplicarMensagemAmigavelErroEnvio') !== false && strpos($metaService, '133010') !== false, 'MetaService mapeia erro 133010 para mensagem amigável');
registerAssert(strpos($workerValidator, "statusMeta !== 'conectado'") !== false, 'worker bloqueia contas não conectadas');
registerAssert(strpos($migration, "'pendente_registro'") !== false && strpos($migration, "'erro_registro'") !== false, 'migration amplia enum de status');
registerAssert(strpos($controller, 'pin\'=>') === false && strpos($controller, 'pin"=>') === false, 'controller não loga PIN em array');

print "Embedded signup register completion tests passed\n";
