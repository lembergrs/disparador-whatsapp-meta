<?php

$root = dirname(__DIR__);
$model = file_get_contents($root . '/app/Models/ConsumoMensal.php');
$migration = file_get_contents($root . '/database/migrations/20260917_separa_consumo_trial_plano.sql');

$checks = [
    'preserva mensagens da avaliação' => strpos($model, 'CMS_MensagensAvaliacao = CMS_Mensagens') !== false,
    'zera franquia ao iniciar plano pago' => strpos($model, 'CMS_Mensagens = 0') !== false,
    'marca transição para impedir novo reset' => strpos($model, 'CMS_InicioPlanoPago = NOW()') !== false,
    'transição é idempotente' => strpos($model, 'AND CMS_InicioPlanoPago IS NULL') !== false,
    'detecta pagamento confirmado' => strpos($model, "CLI_StatusPagamento'] ?? '')) === 'pago'") !== false,
    'migração preserva consumo de pagantes existentes' => strpos($migration, "cli.CLI_StatusPagamento = 'pago'") !== false,
    'migração usa primeiro pagamento como marco' => strpos($migration, 'MIN(COB_DataPagamento) AS PrimeiroPagamento') !== false,
];

$falhas = [];
foreach($checks as $descricao => $ok){
    if(!$ok){
        $falhas[] = $descricao;
    }
}

if($falhas){
    fwrite(STDERR, "Falhas: " . implode(', ', $falhas) . PHP_EOL);
    exit(1);
}

echo "OK - consumo de avaliação separado do plano pago" . PHP_EOL;
