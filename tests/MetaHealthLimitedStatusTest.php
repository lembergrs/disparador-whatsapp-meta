<?php

$view = file_get_contents(__DIR__ . '/../app/Views/configuracao/_meta_health.php');

$assert = static function ($condicao, $mensagem) {
    if(!$condicao){
        fwrite(STDERR, "FALHOU: {$mensagem}\n");
        exit(1);
    }
};

$assert(
    strpos($view, '$canSendMeta === \'BLOCKED\' && !$temErroMetaCritico') !== false,
    'BLOCKED sem erro crítico deve ser reclassificado apenas na apresentação.'
);

$assert(
    strpos($view, '$canSendMetaExibicao = \'LIMITED\';') !== false,
    'A apresentação deve usar LIMITED para limitações não críticas.'
);

$assert(
    strpos($view, "'LIMITED' => ['classe' => 'warning', 'texto' => 'Limitado']") !== false,
    'LIMITED deve continuar sendo exibido como aviso.'
);

$assert(
    strpos($view, "'BLOCKED' => ['classe' => 'danger', 'texto' => 'Bloqueado']") !== false,
    'BLOCKED deve permanecer disponível para diagnósticos críticos.'
);

echo "OK - classificação visual do health_status da Meta validada.\n";
