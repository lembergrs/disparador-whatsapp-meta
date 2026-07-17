<?php

function contatoAssert($cond, $msg){
    if(!$cond){
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$view = file_get_contents($root . '/app/Views/conversas/index.php');
$controller = file_get_contents($root . '/app/Controllers/ConversaController.php');
$contato = file_get_contents($root . '/app/Models/Contato.php');
$service = file_get_contents($root . '/app/Services/ConversaTemplateService.php');

contatoAssert(strpos($view, 'Contato cadastrado') !== false, 'campo de contato aparece no modal');
contatoAssert(strpos($view, 'Pesquisar contato por nome ou telefone') !== false, 'placeholder de pesquisa por nome/telefone existe');
contatoAssert(strpos($view, 'novaContatoResultados') !== false && strpos($view, 'buscarContatosNovaConversa') !== false, 'combobox pesquisa por AJAX sob demanda');
contatoAssert(strpos($view, 'btnLimparContatoNova') !== false && strpos($view, 'limparContatoNovaConversa') !== false, 'seleção pode ser limpa');
contatoAssert(strpos($view, 'name="contato_id"') !== false, 'contato_id fica associado ao formulário');
contatoAssert(strpos($view, 'item-contato-nova') !== false, 'resultados de contato são selecionáveis');
contatoAssert(strpos($view, "$('#novaNomeContato').val(nome)") !== false, 'seleção preenche nome');
contatoAssert(strpos($view, "$('#novaTelefoneDestino').val(telefone).trigger('input')") !== false, 'seleção preenche telefone e aplica fluxo de máscara');
contatoAssert(strpos($view, "$('#novaMetaId').on('change'") !== false && strpos($view, 'limparContatoNovaConversa(true)') !== false, 'troca de remetente limpa contato anterior');
contatoAssert(strpos($view, 'timerBuscaContatoNova') !== false && strpos($view, '}, 300)') !== false, 'busca tem debounce');
contatoAssert(strpos($view, 'termo.length < 2') !== false, 'frontend evita pesquisa antes de 2 caracteres');
contatoAssert(strpos($view, 'resetarModalNovaConversa') !== false, 'modal reseta contato ao reabrir');

contatoAssert(strpos($controller, 'buscarContatosAjax') !== false, 'endpoint de busca de contatos existe');
contatoAssert(strpos($controller, 'buscarPorUsuario($metaId, $usuario)') !== false, 'endpoint valida remetente no escopo');
contatoAssert(strpos($controller, 'mb_strlen($termo') !== false, 'endpoint valida tamanho do termo');
contatoAssert(strpos($controller, '$limite = 20') !== false, 'endpoint limita resultados');
contatoAssert(strpos($controller, 'telefone_formatado') !== false, 'endpoint retorna telefone formatado');
contatoAssert(strpos($controller, 'pagination') !== false, 'endpoint retorna paginação');

contatoAssert(strpos($contato, 'pesquisarPorUsuarioMeta') !== false, 'model pesquisa contatos por usuário e remetente');
contatoAssert(strpos($contato, 'Auth::idsContasMetaPermitidas($usuario)') !== false, 'busca de contatos reutiliza escopo central');
contatoAssert(strpos($contato, 'CON_Nome LIKE ?') !== false, 'busca por nome usa prepared statement');
contatoAssert(strpos($contato, 'CON_Telefone LIKE ?') !== false, 'busca por telefone usa prepared statement');
contatoAssert(strpos($contato, 'LIMIT ? OFFSET ?') !== false, 'busca aplica limite e paginação');
contatoAssert(strpos($contato, 'buscarPorClienteId') !== false, 'model valida contato por cliente');

contatoAssert(strpos($service, '$contatoIdSelecionado') !== false, 'serviço recebe contato_id opcional');
contatoAssert(strpos($service, 'buscarPorClienteId((int) $conta[\'CLI_ID\'], $contatoIdSelecionado)') !== false, 'serviço valida contato_id no CLI_ID da conta');
contatoAssert(strpos($service, 'Telefone informado não corresponde ao contato selecionado') !== false, 'serviço rejeita manipulação de telefone com contato_id');
contatoAssert(strpos($service, 'buscarPorTelefone((int) $conta[\'CLI_ID\'], $telefone)') !== false, 'sem contato_id continua reutilizando por telefone normalizado');

echo "Conversa nova contato tests passed\n";
