<?php

$root = dirname(__DIR__);
function iicAssert($condicao, $mensagem){ if(!$condicao){ fwrite(STDERR, "FAIL: {$mensagem}\n"); exit(1); } }

$controller = file_get_contents($root . '/app/Controllers/IndicacaoController.php');
$leitura = file_get_contents($root . '/app/Services/Indicacao/IndicacaoClienteReadService.php');
$view = file_get_contents($root . '/app/Views/indicacao/index.php');
$auth = file_get_contents($root . '/app/Core/Auth.php');
$menu = file_get_contents($root . '/app/Views/layouts/master.php');

iicAssert(strpos($controller, 'Auth::cliente()') !== false, 'rota exige autenticação de cliente');
iicAssert(strpos($controller, "usuario['CLI_ID']") !== false && strpos($controller, '$_GET[\'CLI_ID\']') === false, 'controller deriva cliente somente da sessão');
foreach(['WHERE CLI_ID=?', 'WHERE CLI_Indicador_ID=?', 'WHERE cr.CLI_Indicador_ID=?'] as $filtro){ iicAssert(strpos($leitura, $filtro) !== false, 'consulta isolada por cliente: ' . $filtro); }
iicAssert(strpos($leitura, 'unset($item[\'CLI_Nome\'], $item[\'CLI_NomeFantasia\'])') !== false, 'lista não retorna nome bruto do indicado');
foreach(['CPF', 'CNPJ', 'Email', 'Telefone', 'IND_ID', 'ICR_ID', 'ICRR_ReferenciaID'] as $dado){ iicAssert(strpos($view, $dado) === false, 'view não expõe ' . $dado); }
iicAssert(strpos($controller, 'site/cadastro&ref=') !== false && strpos($controller, 'rawurlencode') !== false, 'link público usa ref codificado');
iicAssert(strpos($view, "compartilhamento['disponivel']") !== false && strpos($view, 'btn-copiar') !== false, 'CTA de cópia depende de código disponível');
iicAssert(strpos($leitura, "SUM(IND_Status='aguardando_pagamento')") !== false && strpos($leitura, "SUM(ICR_Status='liberado')") !== false, 'totais usam estados persistidos');
foreach(['FIFO', 'prepararDesconto', 'IndicacaoCalculoDescontoService'] as $termo){ iicAssert(strpos($controller . $view, $termo) === false, 'UI não duplica ' . $termo); }
iicAssert(strpos($auth, "'indicacao'") !== false && strpos($menu, 'Indique e Ganhe') !== false, 'rota e menu de cliente adicionados');

echo "IndicacaoInterfaceClienteStaticTest OK\n";
