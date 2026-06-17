<?php

require __DIR__ . '/config/config.php';

if(file_exists(__DIR__ . '/vendor/autoload.php')){
    require __DIR__ . '/vendor/autoload.php';
}

spl_autoload_register(function($class){
    $class = str_replace('\\', '/', $class);
    $file = __DIR__ . '/app/' . $class . '.php';

    if(file_exists($file)){
        require_once $file;
    }
});

use Services\FinanceiroRecorrenciaService;

$service = new FinanceiroRecorrenciaService();
$resultado = $service->processarVencimentos();

echo 'Vencimentos processados com sucesso.' . PHP_EOL;
echo 'Cobranças vencidas: ' . $resultado['cobrancas_vencidas'] . PHP_EOL;
echo 'Assinaturas vencidas: ' . $resultado['assinaturas_vencidas'] . PHP_EOL;
echo 'Clientes atualizados: ' . $resultado['clientes_atualizados'] . PHP_EOL;
echo 'Dias de tolerância: ' . $resultado['dias_tolerancia'] . PHP_EOL;
