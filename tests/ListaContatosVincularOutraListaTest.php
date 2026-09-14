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

$assert(strpos($view, 'id="btnAdicionarLista"') !== false, 'Tela deve oferecer a ação Adicionar à lista.');
$assert(strpos($view, 'Adicionar à lista') !== false, 'A ação deve usar linguagem de vínculo, não de transferência.');
$assert(strpos($view, 'id="modalAdicionarLista"') !== false, 'A seleção da lista de destino deve ocorrer em modal.');
$assert(strpos($view, 'name="lista_destino"') !== false, 'Modal deve enviar a lista de destino escolhida.');
$assert(strpos($view, 'Os contatos continuarão também na lista atual.') !== false, 'Interface deve deixar claro que a lista de origem será preservada.');
$assert(strpos($view, 'botaoAdicionar.disabled = total === 0 || !temListasDestino;') !== false, 'Ação deve ficar desabilitada sem seleção ou sem lista de destino.');
$assert(strpos($view, "input.name = 'contatos[]';") !== false, 'Somente os IDs selecionados devem ser enviados ao vínculo.');

$assert(strpos($controller, "'listasDestino' => \$listasDestino") !== false, 'Visualização deve receber apenas listas de destino disponíveis.');
$assert(strpos($controller, "return (int) \$listaDisponivel['LST_ID'] !== (int) \$id;") !== false, 'A lista atual não pode aparecer como destino.');
$assert(strpos($controller, 'public function vincularContatosSelecionados()') !== false, 'Controller deve possuir endpoint para vínculo em lote.');
$assert(substr_count($controller, '$this->listaModel->buscar(') >= 2, 'Endpoint deve validar listas no escopo do cliente.');
$assert(strpos($controller, '$listaOrigemId === $listaDestinoId') !== false, 'Backend deve rejeitar origem e destino iguais.');
$assert(strpos($controller, '$this->listaItemModel->adicionarContatosDeOutraLista(') !== false, 'Controller deve delegar o vínculo em lote ao model.');

$assert(strpos($model, 'public function adicionarContatosDeOutraLista(') !== false, 'Model deve possuir vínculo em lote entre listas.');
$assert(strpos($model, 'INSERT IGNORE INTO lista_contatos_itens (LST_ID, CON_ID)') !== false, 'Vínculo deve ser idempotente.');
$assert(strpos($model, 'FROM lista_contatos_itens origem') !== false, 'IDs vinculados devem necessariamente pertencer à lista de origem.');
$assert(strpos($model, 'WHERE origem.LST_ID = ?') !== false, 'Seleção deve ser limitada à lista de origem.');
$assert(strpos($model, 'DELETE FROM lista_contatos_itens') !== false, 'Remoção existente deve permanecer disponível.');
$assert(strpos($model, 'DELETE FROM contatos') === false, 'Vínculo nunca pode excluir o contato da base.');

echo "Lista contatos link-to-another-list checks passed\n";
