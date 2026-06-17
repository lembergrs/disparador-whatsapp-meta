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
$resultado = $service->gerarCobrancasRecorrentes();

echo 'Cobranças recorrentes processadas.' . PHP_EOL;
echo 'Cobranças geradas: ' . $resultado['cobrancas_geradas'] . PHP_EOL;
echo 'Assinaturas processadas: ' . $resultado['assinaturas_processadas'] . PHP_EOL;
echo 'Ignoradas por duplicidade: ' . $resultado['cobrancas_ignoradas_duplicidade'] . PHP_EOL;
echo 'Erros: ' . $resultado['erros'] . PHP_EOL;
