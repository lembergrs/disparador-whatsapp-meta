<?php

$root = dirname(__DIR__);

function iws($valor, $mensagem)
{
    if(!$valor){
        fwrite(STDERR, "FAIL: {$mensagem}\n");
        exit(1);
    }
}

$arquivo = $root . '/app/Services/Indicacao/IndicacaoWorkflowService.php';
iws(is_file($arquivo), 'workflow existe');
$source = file_get_contents($arquivo);
iws(strpos($source, 'CodigoIndicacaoNormalizer::normalizar') !== false, 'normalização central');
iws(strpos($source, 'beginTransaction') !== false && strpos($source, 'rollBack') !== false, 'transação e rollback');
iws(strpos($source, "'aguardando_pagamento'") !== false, 'estado operacional inicial');
iws(strpos($source, 'IND_UUID') === false, 'sem UUID');
foreach(['Asaas','TaskScheduler','IndicacaoCredito','COB_ID','ASS_ID'] as $termo){
    iws(strpos($source, $termo) === false, "sem dependência {$termo}");
}

echo "IndicacaoWorkflowStaticTest OK\n";
