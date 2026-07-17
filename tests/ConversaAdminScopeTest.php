<?php

function conversaAdminAssert($condition, $message)
{
    if(!$condition){
        throw new RuntimeException($message);
    }
}

$auth = file_get_contents(__DIR__ . '/../app/Core/Auth.php');
$model = file_get_contents(__DIR__ . '/../app/Models/Conversa.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ConversaController.php');
$layout = file_get_contents(__DIR__ . '/../app/Views/layouts/master.php');

conversaAdminAssert(strpos($auth, 'idsContasMetaPermitidas') !== false, 'Auth deve centralizar ids de contas Meta permitidas.');
conversaAdminAssert(strpos($auth, "WHERE CLI_ID = ?") !== false && strpos($auth, "MTA_Ativo = 'S'") !== false, 'Escopo de contas Meta deve usar CLI_ID e contas ativas.');
conversaAdminAssert(strpos($auth, 'return [];') !== false, 'Escopo sem vínculo válido deve retornar lista vazia.');

conversaAdminAssert(strpos($controller, 'Auth::check();') !== false, 'ConversaController deve permitir avaliação de admin autenticado.');
conversaAdminAssert(strpos($controller, "(\$usuario['nivel'] ?? null) === 'admin'") !== false, 'Controller deve permitir admin no módulo Conversas.');
conversaAdminAssert(strpos($controller, "['admin', 'cliente_admin', 'cliente']") !== false, 'Gerenciamento deve incluir admin sem remover clientes atuais.');
conversaAdminAssert(strpos($controller, 'buscarAcessivel(') !== false, 'Endpoints devem validar acesso por conversa.');
conversaAdminAssert(strpos($controller, 'marcarComoLida(') !== false && strpos($controller, '$usuario') !== false, 'Marcação como lida deve receber o usuário para escopo.');
conversaAdminAssert(strpos($controller, 'atribuirResponsavel(') !== false && strpos($controller, '$usuario') !== false, 'Atribuição deve passar pelo escopo do usuário.');

conversaAdminAssert(strpos($model, 'aplicarEscopoUsuario') !== false, 'Model deve aplicar escopo centralizado nas consultas.');
conversaAdminAssert(strpos($model, 'Auth::idsContasMetaPermitidas($usuario)') !== false, 'Model deve reutilizar ids permitidos do Auth.');
conversaAdminAssert(strpos($model, "MTA_ID IN") !== false, 'Consultas devem filtrar por contas Meta permitidas.');
conversaAdminAssert(strpos($model, '1 = 0') !== false, 'Sem vínculo válido não deve haver fallback global.');
conversaAdminAssert(strpos($model, 'COUNT(DISTINCT c.CVS_ID)') !== false, 'Badge deve contar conversas distintas, não mensagens brutas.');
conversaAdminAssert(strpos($model, "c.CVS_NaoLida = 'S'") !== false, 'Contagem deve usar regra real de conversa não lida.');
conversaAdminAssert(strpos($model, 'totalConversasNaoLidasPorUsuario') !== false, 'Model deve expor total não lido por escopo.');

$listarPos = strpos($model, 'public function listarConversas');
$listarTrecho = substr($model, $listarPos, strpos($model, 'public function listarMensagens') - $listarPos);
conversaAdminAssert(strpos($listarTrecho, 'aplicarEscopoUsuario') !== false, 'Listagem deve usar escopo centralizado.');

$buscarPos = strpos($model, 'public function buscar(');
$buscarTrecho = substr($model, $buscarPos, strpos($model, 'public function buscarAcessivel') - $buscarPos);
conversaAdminAssert(strpos($buscarTrecho, 'aplicarEscopoUsuario') !== false, 'Abertura de conversa deve usar escopo centralizado.');

conversaAdminAssert(strpos($layout, 'url=conversa') !== false, 'Menu deve apontar para rota real de Conversas.');
conversaAdminAssert(strpos($layout, "usuario['nivel'] == 'admin'") !== false && strpos($layout, 'Conversas') !== false, 'Menu admin deve exibir Conversas.');
conversaAdminAssert(strpos($layout, 'totalConversasNaoLidasPorUsuario($usuario)') !== false, 'Badge deve carregar total pelo escopo do usuário logado.');
conversaAdminAssert(strpos($layout, 'badge badge-danger right') !== false, 'Badge deve usar padrão AdminLTE.');
conversaAdminAssert(strpos($layout, "<?= \$totalConversasNaoLidas > 99 ? '99+'") !== false, 'Badge deve limitar visualmente acima de 99.');
conversaAdminAssert(substr_count($layout, 'if($totalConversasNaoLidas > 0)') >= 2, 'Badge deve ficar oculto quando total for zero para admin e cliente.');

print "Conversa admin scope tests passed\n";
