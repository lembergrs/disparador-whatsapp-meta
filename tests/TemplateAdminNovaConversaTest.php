<?php

function adminTplAssert($cond, $msg){
    if(!$cond){
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$templateController = file_get_contents($root . '/app/Controllers/TemplateController.php');
$templateModel = file_get_contents($root . '/app/Models/TemplateMeta.php');
$metaModel = file_get_contents($root . '/app/Models/MetaConta.php');
$conversaController = file_get_contents($root . '/app/Controllers/ConversaController.php');
$conversaView = file_get_contents($root . '/app/Views/conversas/index.php');
$layout = file_get_contents($root . '/app/Views/layouts/master.php');
$service = file_get_contents($root . '/app/Services/ConversaTemplateService.php');
$conversaModel = file_get_contents($root . '/app/Models/Conversa.php');

adminTplAssert(strpos($templateController, 'Auth::clienteAdmin()') !== false, 'Templates devem aceitar admin, cliente e cliente_admin.');
adminTplAssert(strpos($templateController, 'listarPorUsuario($usuario)') !== false, 'Listagem de templates deve usar escopo por usuário.');
adminTplAssert(strpos($templateController, 'buscarPorUsuario($metaId, $usuario)') !== false, 'Sincronização/criação deve validar conta Meta no escopo.');
adminTplAssert(strpos($templateController, 'inativarPorUsuario') !== false, 'Exclusão local deve validar template no escopo.');
adminTplAssert(strpos($templateModel, 'Auth::idsContasMetaPermitidas($usuario)') !== false, 'TemplateMeta deve reutilizar Auth::idsContasMetaPermitidas.');
adminTplAssert(strpos($templateModel, 'return [\'1 = 0\'') !== false || strpos($templateModel, "return ['1 = 0'") !== false, 'Escopo vazio deve gerar 1 = 0.');
adminTplAssert(strpos($templateModel, 'listarAprovadosParaEnvioPorUsuarioConta') !== false, 'Deve listar templates aprovados por conta permitida.');
adminTplAssert(strpos($templateModel, "t.TMP_Status = 'APPROVED'") !== false, 'Nova conversa só deve carregar templates aprovados.');
adminTplAssert(strpos($metaModel, 'buscarPorUsuario') !== false && strpos($metaModel, 'idsContasMetaPermitidas') !== false, 'MetaConta deve validar MTA_ID permitido.');
adminTplAssert(strpos($layout, 'Templates Meta') !== false && strpos($layout, "usuario['nivel'] == 'admin'") !== false, 'Menu admin deve exibir Templates Meta uma vez no bloco admin.');
adminTplAssert(strpos($conversaView, 'Nova conversa') !== false && strpos($conversaView, 'modalNovaConversa') !== false, 'Conversas deve exibir modal Nova conversa.');
adminTplAssert(strpos($conversaView, 'opcoesValidas.length === 1') !== false, 'Nova conversa deve selecionar automaticamente remetente único.');
adminTplAssert(strpos($conversaView, "$('.telefone-br').unmask().mask") !== false, 'Nova conversa deve aplicar máscara dinâmica sem acumular handlers.');
adminTplAssert(strpos($service, "if(strlen($" . "numero) === 12 || strlen($" . "numero) === 13)") !== false, 'Normalização deve aceitar DDI 55 informado sem duplicar.');
adminTplAssert(strpos($conversaController, 'templatesAprovadosAjax') !== false, 'Endpoint AJAX para templates aprovados deve existir.');
adminTplAssert(strpos($conversaController, 'iniciarPorTemplateAjax') !== false, 'Endpoint AJAX para iniciar conversa por template deve existir.');
adminTplAssert(strpos($service, 'new MetaService($metaId, (int) $conta[\'CLI_ID\'])') !== false, 'Envio deve usar token/conta Meta validada.');
adminTplAssert(strpos($service, 'buscarAprovadoParaEnvioPorUsuario') !== false, 'Serviço deve validar template aprovado no backend.');
adminTplAssert(strpos($service, 'beginTransaction') !== false && strpos($service, 'commit') !== false, 'Persistência de contato/conversa/mensagem deve usar transação curta.');
adminTplAssert(strpos($service, "'tipo' => 'template'") !== false, 'Mensagem deve ser registrada como template.');
adminTplAssert(strpos($conversaModel, "CVS_NaoLida = 'S'") !== false && strpos($conversaModel, "if($" . "direcao == 'recebida')") !== false, 'Badge continua dependente de mensagens recebidas não lidas.');

echo "Template admin e nova conversa static tests passed\n";
