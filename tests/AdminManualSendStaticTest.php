<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/DisparoController.php');
$model = file_get_contents($root . '/app/Models/DisparoManual.php');
$view = file_get_contents($root . '/app/Views/disparos/index.php');
$layout = file_get_contents($root . '/app/Views/layouts/master.php');

$assert(strpos($controller, 'Auth::check()') !== false, 'Disparo deve permitir autenticação antes da checagem de nível.');
$assert(strpos($controller, "($usuario['nivel'] ?? null) !== 'admin'") !== false, 'Admin deve ser aceito explicitamente.');
$assert(strpos($controller, 'clienteOperacaoPorMeta') !== false, 'Envio admin deve resolver o cliente pela conta Meta.');
$assert(strpos($controller, 'listarPorUsuario($usuario)') !== false, 'Tela admin deve listar somente contas permitidas ao usuário logado.');
$assert(strpos($controller, 'buscarPorUsuario($metaId, $usuario)') !== false, 'Conta selecionada deve ser validada no escopo do usuário.');
$assert(strpos($controller, "return (int) $conta['CLI_ID'];") !== false, 'Escopo do envio deve usar o CLI_ID proprietário da conta permitida.');
$assert(strpos($controller, 'clienteOperacaoPorLote') !== false, 'Processamento da fila deve recuperar o escopo do usuário.');
$assert(strpos($model, 'public function buscarLoteAdmin') === false, 'Não deve existir acesso administrativo amplo a lotes.');
$assert(strpos($view, '$adminMode = !empty($adminMode);') !== false, 'Tela deve ter modo administrativo explícito.');
$assert(strpos($view, 'Envio administrativo') !== false, 'Tela deve explicar o contexto do envio admin.');
$assert(strpos($view, "if(!$adminMode)") !== false, 'Recursos específicos do cliente devem permanecer ocultos no modo admin.');
$assert(strpos($layout, 'index.php?url=disparo') !== false && strpos($layout, '<p>Disparos</p>') !== false, 'Menu admin deve exibir Disparos.');

echo "Admin manual send static checks passed\n";
