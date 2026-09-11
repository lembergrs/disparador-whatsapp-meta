<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$root = dirname(__DIR__);
$view = file_get_contents($root . '/app/Views/listas/visualizar.php');
$controller = file_get_contents($root . '/app/Controllers/ListaContatoController.php');
$model = file_get_contents($root . '/app/Models/ListaContatoItem.php');

$assert(strpos($view, 'id="btnExcluirSelecionados"') !== false, 'Tela deve exibir a ação Excluir selecionados.');
$assert(strpos($view, 'id="btnExcluirSelecionados"') < strpos($view, 'disabled'), 'A ação em massa deve iniciar desabilitada.');
$assert(strpos($view, 'class="contato-selecao mr-2"') !== false, 'Cada contato deve ter checkbox antes do nome.');
$assert(strpos($view, 'id="selecionarTodosPagina"') !== false, 'Cabeçalho deve permitir selecionar os contatos visíveis da página.');
$assert(strpos($view, 'var contatosSelecionados = new Set();') !== false, 'Seleção deve sobreviver à paginação/redesenho do DataTables.');
$assert(strpos($view, "input.name = 'contatos[]';") !== false, 'Envio deve incluir somente os IDs selecionados.');
$assert(strpos($view, 'continuarão cadastrados no sistema') !== false, 'Confirmação deve deixar claro que os contatos não serão excluídos do sistema.');

$assert(strpos($controller, 'public function removerContatosSelecionados()') !== false, 'Controller deve possuir endpoint de remoção múltipla.');
$assert(strpos($controller, '$this->validarCsrfPost();') !== false, 'Remoção múltipla deve preservar proteção CSRF.');
$assert(strpos($controller, "$this->listaModel->buscar") !== false, 'Controller deve validar a lista no escopo do cliente.');
$assert(strpos($controller, "$this->listaItemModel->removerContatos") !== false, 'Controller deve remover somente vínculos da lista.');

$assert(strpos($model, 'public function removerContatos($listaId, array $contatoIds)') !== false, 'Model deve oferecer remoção em lote.');
$assert(strpos($model, 'DELETE FROM lista_contatos_itens') !== false, 'Remoção deve atuar na tabela de vínculos da lista.');
$assert(strpos($model, 'WHERE LST_ID = ?') !== false && strpos($model, 'AND CON_ID IN ({$placeholders})') !== false, 'DELETE deve permanecer limitado à lista e aos contatos selecionados.');
$assert(strpos($model, 'DELETE FROM contatos') === false, 'Fluxo não pode excluir contatos da base do cliente.');

echo "Lista contatos bulk removal checks passed\n";
