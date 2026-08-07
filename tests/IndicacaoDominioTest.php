<?php

require __DIR__ . '/../vendor/autoload.php';

use Services\Indicacao\CodigoIndicacaoNormalizer;
use Services\Indicacao\CodigoIndicacaoPadraoGenerator;
use Services\Indicacao\IndicacaoStatusTransitionService;

function ok($cond, $msg){ if(!$cond){ fwrite(STDERR, "FALHA: {$msg}\n"); exit(1); } }

$normalizer = new CodigoIndicacaoNormalizer();
ok($normalizer->normalizar(' rod-8xj4p ') === 'ROD-8XJ4P', 'normalização central');

$gerador = new CodigoIndicacaoPadraoGenerator();
foreach([
    ['CLI_NomeFantasia'=>'Rodrigo Lemberg Sistemas','prefixo'=>'RLS'],
    ['CLI_NomeFantasia'=>'Árvore Digital','prefixo'=>'ADR'],
    ['CLI_NomeFantasia'=>'','CLI_RazaoSocial'=>'','CLI_Nome'=>'','prefixo'=>'DSP'],
] as $caso){
    $codigo = $gerador->gerar($caso);
    ok(str_starts_with($codigo, $caso['prefixo'] . '-'), 'prefixo esperado');
    ok((bool)preg_match('/^[A-Z0-9]{3}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{5}$/', $codigo), 'formato e alfabeto seguros');
    ok(!preg_match('/[IO01]/', substr($codigo,4)), 'sufixo sem caracteres ambíguos');
}

$trans = new IndicacaoStatusTransitionService();
$trans->validar('indicacao','aguardando_pagamento','pagamento_confirmado');
$falhou = false;
try{ $trans->validar('indicacao','cadastrada','aprovada'); }catch(InvalidArgumentException $e){ $falhou = true; }
ok($falhou, 'transição inválida bloqueada');

echo "IndicacaoDominioTest: OK\n";
