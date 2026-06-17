<?php

require_once __DIR__ . '/../app/Models/Plano.php';

use Models\Plano;

$plano = [
    'PLA_Valor' => '100.00',
    'PLA_ValorMensal' => '100.00',
    'PLA_ValorTrimestral' => '',
    'PLA_ValorSemestral' => null,
    'PLA_ValorAnual' => '1000.00'
];

$casos = [
    'mensal' => 100.00,
    'trimestral' => 300.00,
    'semestral' => 600.00,
    'anual' => 1000.00
];

foreach($casos as $ciclo => $esperado){
    $valor = Plano::valorPorCiclo($plano, $ciclo);

    if(abs($valor - $esperado) > 0.0001){
        throw new Exception(
            "Valor incorreto para {$ciclo}: esperado {$esperado}, recebido {$valor}"
        );
    }
}

$planoAntigo = [
    'PLA_Valor' => '50.00'
];

if(Plano::valorPorCiclo($planoAntigo, 'anual') !== 600.0){
    throw new Exception('Fallback de plano antigo com PLA_Valor falhou.');
}

if(!Plano::cicloValido('mensal') || Plano::cicloValido('bienal')){
    throw new Exception('Validação de ciclos falhou.');
}

echo "Valores por ciclo de plano OK\n";
