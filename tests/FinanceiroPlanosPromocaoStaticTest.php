<?php

function planosPromocaoAssert($condition, $message)
{
    if(!$condition){
        throw new RuntimeException($message);
    }
}

$controller = file_get_contents(__DIR__ . '/../app/Controllers/FinanceiroController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/financeiro/index.php');
$workflow = file_get_contents(__DIR__ . '/../app/Services/FinanceiroWorkflowService.php');

planosPromocaoAssert(strpos($controller, 'ofertasParaContratacao') !== false, 'controller deve obter a oferta da regra financeira');
planosPromocaoAssert(strpos($view, 'no primeiro pagamento') !== false, 'card deve explicar o primeiro pagamento');
planosPromocaoAssert(strpos($view, 'Depois') !== false, 'card deve mostrar o preço recorrente normal');
planosPromocaoAssert(strpos($view, "empty(\$ofertaMensalPlano['elegivel'])") !== false, 'promoção deve depender da elegibilidade');
planosPromocaoAssert(strpos($view, '$valorMensalPlano / 2') === false, 'view não deve recalcular desconto promocional');
planosPromocaoAssert(substr_count($workflow, 'calcularDescontoInicialCentavos(') >= 3, 'prévia e cobrança devem compartilhar o mesmo cálculo');
planosPromocaoAssert(strpos($view, "number_format(\$assinaturaAtual['ASS_Valor']") !== false, 'plano atual deve continuar exibindo o valor vigente');

echo "FinanceiroPlanosPromocaoStaticTest OK\n";
