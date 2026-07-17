<?php

require_once __DIR__ . '/../app/Services/ConversaTemplateService.php';

use Services\ConversaTemplateService;

function telAssert($cond, $msg){
    if(!$cond){
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$service = (new ReflectionClass(ConversaTemplateService::class))->newInstanceWithoutConstructor();
$normalizar = function($valor) use ($service){
    return $service->normalizarTelefone($valor);
};

$casos = [
    '(41) 99999-9999' => '5541999999999',
    '41 99999-9999' => '5541999999999',
    '+55 (41) 99999-9999' => '5541999999999',
    '5541999999999' => '5541999999999',
    '(41) 3333-4444' => '554133334444',
    '41 3333-4444' => '554133334444'
];

foreach($casos as $entrada => $esperado){
    telAssert($normalizar($entrada) === $esperado, "$entrada normaliza para $esperado");
}

telAssert($normalizar('+55 (41) 99999-9999') !== '555541999999999', 'não duplica DDI 55');

foreach(['9999-9999', '123', '00415541999999999', '554199999'] as $entradaInvalida){
    $falhou = false;
    try{
        $normalizar($entradaInvalida);
    }catch(Exception $e){
        $falhou = true;
    }
    telAssert($falhou, "$entradaInvalida deve ser rejeitado");
}

$view = file_get_contents(__DIR__ . '/../app/Views/conversas/index.php');
telAssert(strpos($view, 'Telefone do destinatário com DDI') === false, 'modal não solicita DDI no rótulo');
telAssert(strpos($view, 'placeholder="(41) 99999-9999"') !== false, 'placeholder brasileiro está presente');
telAssert(strpos($view, 'telefone-br') !== false, 'campo usa classe de máscara brasileira');
telAssert(strpos($view, "$('.telefone-br').unmask().mask") !== false, 'máscara reutiliza jQuery Mask sem acumular handlers');
telAssert(strpos($view, "'(00) 00000-0000'") !== false && strpos($view, "'(00) 0000-00009'") !== false, 'máscara dinâmica suporta celular e fixo');
telAssert(strpos($view, 'opcoesValidas.length === 1') !== false, 'remetente único é detectado sem contar placeholder');
telAssert(strpos($view, "select.val(valorUnico).trigger('change')") !== false, 'seleção automática dispara evento change');
telAssert(strpos($view, 'ultimoMetaTemplatesCarregado') !== false, 'controle evita chamada AJAX duplicada');
telAssert(strpos($view, 'requisicaoTemplatesNovaConversa.abort()') !== false, 'requisição anterior é abortada antes de nova carga');
telAssert(strpos($view, 'telefoneBrasileiroValido') !== false, 'frontend valida quantidade de dígitos antes do envio');
telAssert(strpos($view, 'telefoneSemDdiBrasil') !== false && strpos($view, "digitos.substring(2)") !== false, 'frontend tolera cola com DDI 55 sem duplicar visualmente');

echo "Conversa nova telefone tests passed\n";
