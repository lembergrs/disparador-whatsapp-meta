<?php

require_once __DIR__ . '/../app/Services/MetaService.php';

use Services\MetaService;

$validos = [
    '{{1}}',
    '{{2}}',
    '{{123}}',
    '{{nome}}',
    '{{valor}}',
    '{{erro}}',
    '{{cliente_nome}}',
    '{{data_vencimento}}',
    '{{cpf}}',
    'Olá {{nome}}, erro {{erro}}',
    '{{nome}} repetido {{nome}}'
];

$invalidos = [
    '{{ }}',
    '{{nome completo}}',
    '{{nome-usuario}}',
    '{{nome}',
    '{nome}',
    'nome}}'
];

foreach($validos as $texto){
    if(!MetaService::validarSintaxeVariaveisTemplate($texto)){
        fwrite(STDERR, "Falhou ao aceitar variável válida: {$texto}\n");
        exit(1);
    }
}

foreach($invalidos as $texto){
    if(MetaService::validarSintaxeVariaveisTemplate($texto)){
        fwrite(STDERR, "Falhou ao bloquear variável inválida: {$texto}\n");
        exit(1);
    }
}

echo "Validação de variáveis de template OK\n";
