<?php

require_once __DIR__ . '/../app/Models/Plano.php';
require_once __DIR__ . '/../app/Services/DescontoBoasVindasService.php';

use Services\DescontoBoasVindasService;

function dbvAssert($condition, $message){ if(!$condition){ throw new RuntimeException($message); } }

$servico = new DescontoBoasVindasService();
$basico = [
    'PLA_ID'=>1,
    'PLA_ValorMensal'=>'97.90',
    'PLA_ValorTrimestral'=>'279.02',
    'PLA_ValorSemestral'=>'528.66',
    'PLA_ValorAnual'=>'998.58'
];

$esperadosBasico = [
    'mensal'=>4895,
    'trimestral'=>23007,
    'semestral'=>47971,
    'anual'=>94963
];
foreach($esperadosBasico as $ciclo=>$esperado){
    $oferta = $servico->calcular($basico, $ciclo);
    dbvAssert($oferta['desconto_centavos'] === 4895, 'Básico deve descontar R$ 48,95 em ' . $ciclo);
    dbvAssert($oferta['primeira_cobranca_centavos'] === $esperado, 'primeira cobrança incorreta para Básico ' . $ciclo);
}

$profissional = ['PLA_ValorMensal'=>'197.90','PLA_ValorAnual'=>'2018.58'];
$empresarial = ['PLA_ValorMensal'=>'397.90','PLA_ValorAnual'=>'4058.58'];
dbvAssert($servico->calcular($profissional, 'anual')['primeira_cobranca_centavos'] === 191963, 'Profissional anual deve cobrar R$ 1.919,63');
dbvAssert($servico->calcular($empresarial, 'anual')['primeira_cobranca_centavos'] === 385963, 'Empresarial anual deve cobrar R$ 3.859,63');

$arredondamento = $servico->calcular(['PLA_ValorMensal'=>'99.99'], 'mensal');
dbvAssert($arredondamento['desconto_centavos'] === 5000 && $arredondamento['primeira_cobranca_centavos'] === 4999, 'meio centavo deve arredondar para cima');
$limitado = $servico->calcular(['PLA_ValorMensal'=>'100.00','PLA_ValorAnual'=>'10.00'], 'anual');
dbvAssert($limitado['desconto_centavos'] === 1000 && $limitado['primeira_cobranca_centavos'] === 0, 'desconto não pode tornar cobrança negativa');
$semValor = $servico->calcular(['PLA_ValorMensal'=>'-10.00','PLA_Valor'=>'0'], 'mensal');
dbvAssert($semValor['desconto_centavos'] === 0 && $semValor['primeira_cobranca_centavos'] === 0, 'valores inválidos não podem gerar desconto negativo');

$cicloInvalido = false;
try{ $servico->calcular($basico, 'quinzenal'); }catch(DomainException $e){ $cicloInvalido = true; }
dbvAssert($cicloInvalido, 'ciclo inválido deve ser rejeitado');

echo "DescontoBoasVindasServiceTest OK\n";
