<?php

$arquivo = __DIR__ . '/../app/Models/Campanha.php';
$conteudo = file_get_contents($arquivo);

function assertCampoResetado($metodo, $campo, $prefixo = '')
{
    if(strpos($metodo, $prefixo . $campo . ' = NULL') === false && strpos($metodo, $prefixo . $campo . ' = 0') === false && strpos($metodo, $prefixo . $campo . " = 'pendente'") === false){
        fwrite(STDERR, "Campo não resetado no reagendamento: {$prefixo}{$campo}\n");
        exit(1);
    }
}

function extrairMetodo($conteudo, $nome)
{
    $inicio = strpos($conteudo, 'function ' . $nome . '(');
    if($inicio === false){
        fwrite(STDERR, "Método não encontrado: {$nome}\n");
        exit(1);
    }

    $proximo = strpos($conteudo, 'public function ', $inicio + 1);
    if($proximo === false){
        $proximo = strlen($conteudo);
    }

    return substr($conteudo, $inicio, $proximo - $inicio);
}

$camposNulos = [
    'FIL_MessageId',
    'FIL_DataEnvio',
    'FIL_WorkerId',
    'FIL_DataReserva',
    'FIL_ProximaTentativa',
    'FIL_UltimoErroTipo',
    'FIL_UltimoErroCodigo',
    'FIL_Erro',
    'FIL_Retorno',
];

foreach([
    'resetarFila' => '',
    'resetarFilaPorCliente' => 'f.',
] as $metodoNome => $prefixo){
    $metodo = extrairMetodo($conteudo, $metodoNome);
    assertCampoResetado($metodo, 'FIL_Status', $prefixo);
    assertCampoResetado($metodo, 'FIL_Tentativas', $prefixo);

    foreach($camposNulos as $campo){
        assertCampoResetado($metodo, $campo, $prefixo);
    }
}

echo "Campanha reagendamento static checks passed\n";
