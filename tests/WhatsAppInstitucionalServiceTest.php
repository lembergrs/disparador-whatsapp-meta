<?php

require_once __DIR__ . '/../app/Services/EventoNotificacao.php';
require_once __DIR__ . '/../app/Services/WhatsAppInstitucionalService.php';

use Services\EventoNotificacao;
use Services\WhatsAppInstitucionalService;

function waAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

$base = ['phone_number_id'=>'institucional-123', 'waba_id'=>'waba-institucional', 'access_token'=>'TOKEN_SUPER_SECRETO', 'api_version'=>'v23.0', 'idioma'=>'pt_BR', 'timeout'=>3];
$captura = [];
$sucesso = new WhatsAppInstitucionalService($base, function($url, $payload, $config) use (&$captura){ $captura=compact('url','payload','config'); return ['http_code'=>200, 'body'=>'{"messages":[{"id":"wamid.123"}]}']; });
$preparado = $sucesso->preparar(EventoNotificacao::BOAS_VINDAS, ['telefone'=>'(11) 99999-1234', 'nome'=>'Ana']);
waAssert($preparado['telefone'] === '5511999991234', 'telefone brasileiro deve ganhar DDI sem duplicação');
$resultado = $sucesso->enviarPreparado($preparado);
waAssert($resultado['sucesso'] && $resultado['message_id'] === 'wamid.123', 'sucesso deve retornar message_id');
waAssert($captura['payload']['template']['name'] === 'boas_vindas_cadastro' && $captura['payload']['template']['components'][0]['parameters'][0]['text'] === 'Ana', 'boas-vindas deve usar template e nome em {{1}}');
waAssert(strpos($captura['url'], 'institucional-123/messages') !== false && strpos(json_encode($captura['payload']), 'TOKEN_SUPER_SECRETO') === false, 'origem deve ser institucional e token não pode entrar no payload');

$semId = new WhatsAppInstitucionalService($base, function(){ return ['http_code'=>200, 'body'=>'{}']; });
waAssert($semId->enviarEvento(EventoNotificacao::CADASTRO_PENDENTE_CONEXAO, ['telefone'=>'5511999991234'])['sucesso'], 'sucesso sem message_id deve ser aceito');
foreach([[400,'erro_definitivo'],[401,'erro_definitivo'],[429,'erro_temporario'],[500,'erro_temporario']] as $caso){
    $svc = new WhatsAppInstitucionalService($base, function() use ($caso){ return ['http_code'=>$caso[0], 'body'=>'{"error":{"code":100}}']; });
    $r = $svc->enviarEvento(EventoNotificacao::CADASTRO_PENDENTE_CONEXAO, ['telefone'=>'5511999991234']);
    waAssert($r['status'] === $caso[1], 'classificação HTTP incorreta: ' . $caso[0]);
}
$invalida = new WhatsAppInstitucionalService($base, function(){ return ['http_code'=>200, 'body'=>'não-json']; });
waAssert($invalida->enviarEvento(EventoNotificacao::CADASTRO_PENDENTE_CONEXAO, ['telefone'=>'5511999991234'])['error_code'] === 'resposta_invalida', 'resposta inválida deve ser controlada');
$timeout = new WhatsAppInstitucionalService($base, function(){ throw new RuntimeException('timeout TOKEN_SUPER_SECRETO'); });
$timeoutResult = $timeout->enviarEvento(EventoNotificacao::CADASTRO_PENDENTE_CONEXAO, ['telefone'=>'5511999991234']);
waAssert($timeoutResult['status'] === 'erro_temporario' && strpos(json_encode($timeoutResult), 'TOKEN_SUPER_SECRETO') === false, 'timeout deve ser temporário e não vazar token');
$semToken = new WhatsAppInstitucionalService(array_merge($base, ['access_token'=>'']), function(){ throw new RuntimeException('não deve chamar'); });
waAssert($semToken->enviarEvento(EventoNotificacao::CADASTRO_PENDENTE_CONEXAO, ['telefone'=>'5511999991234'])['error_code'] === 'configuracao_ausente', 'token ausente deve impedir chamada');
$semPhoneId = new WhatsAppInstitucionalService(array_merge($base, ['phone_number_id'=>'']), function(){ throw new RuntimeException('não deve chamar'); });
waAssert($semPhoneId->enviarEvento(EventoNotificacao::CADASTRO_PENDENTE_CONEXAO, ['telefone'=>'5511999991234'])['error_code'] === 'configuracao_ausente', 'phone id ausente deve impedir chamada');
waAssert(WhatsAppInstitucionalService::normalizarTelefone('123') === null, 'telefone inválido deve ser recusado');
waAssert(WhatsAppInstitucionalService::normalizarTelefone('+1 212 555 0123') === '12125550123', 'telefone internacional explícito não deve receber DDI brasileiro');
waAssert(WhatsAppInstitucionalService::template(EventoNotificacao::META_CONECTADA) === 'conexao_meta_concluida', 'template Meta conectado centralizado');

echo "WhatsAppInstitucionalServiceTest OK\n";
