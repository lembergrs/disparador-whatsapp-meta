<?php
require_once __DIR__ . '/../app/Services/MensagemStatusService.php';
require_once __DIR__ . '/../app/Services/MetaErrorMessageService.php';

use Services\MetaErrorMessageService;
use Services\MensagemStatusService;

$assert = static function ($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
};
$original = 'In order to maintain a healthy ecosystem engagement, the message failed to be delivered.';
$translated = MetaErrorMessageService::amigavel(131049, $original);
$assert(strpos($translated, '24 horas') !== false, 'Orientação de marketing');
$assert(MetaErrorMessageService::amigavel(null, $original) === $translated, 'Erro histórico');
$assert(strpos(MetaErrorMessageService::amigavel(131050), 'Não reenvie') !== false, 'Preferência do destinatário');
$assert(MetaErrorMessageService::amigavel(999999, 'Erro desconhecido') === 'Erro desconhecido', 'Fallback');
$assert(strpos(MetaErrorMessageService::amigavel(999999, 'token=segredo'), 'segredo') === false, 'Sanitização');
$assert(MetaErrorMessageService::codigoDoRetorno('{"error_code":131049}') === '131049', 'Código webhook');
$assert(strpos(MensagemStatusService::apresentacao('failed', 131049, $original)['tooltip'], '24 horas') !== false, 'Tooltip');
$detalhes = MetaErrorMessageService::detalhes(null, $original);
$assert($detalhes['resumo'] === 'Restrição de marketing (131049)', 'Resumo histórico');
$assert($detalhes['original'] === $original, 'Mensagem técnica preservada');
$assert(strpos($detalhes['explicacao'], '24 horas') !== false, 'Orientação completa');
$assert(MetaErrorMessageService::detalhes(['error'=>['code'=>999999,'message'=>'token=segredo']], 'Falha')['original'] === 'token=[removido]', 'Detalhes sanitizados');
echo "MetaErrorMessageServiceTest OK\n";
