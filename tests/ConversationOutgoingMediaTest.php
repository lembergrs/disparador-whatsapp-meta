<?php

function centralAttachmentAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ConversaController.php');
$meta = file_get_contents($root . '/app/Services/MetaService.php');
$media = file_get_contents($root . '/app/Services/MetaMediaService.php');
$painel = file_get_contents($root . '/app/Views/conversas/partials/painel.php');
$index = file_get_contents($root . '/app/Views/conversas/index.php');

centralAttachmentAssert(strpos($meta, 'public function enviarMidia(') !== false, 'MetaService deve enviar mídia por media_id');
centralAttachmentAssert(strpos($meta, "'type' => $tipo") !== false, 'payload deve usar o tipo da mídia');
centralAttachmentAssert(strpos($meta, "\$conteudo = ['id' => \$mediaId]") !== false, 'payload deve referenciar media_id');
centralAttachmentAssert(strpos($meta, "\$conteudo['caption'] = \$caption") !== false, 'mídia deve aceitar legenda');
centralAttachmentAssert(strpos($meta, "\$conteudo['filename']") !== false, 'documento deve preservar filename');

centralAttachmentAssert(strpos($media, 'public function uploadMensagemMedia') !== false, 'deve reutilizar uploadMensagemMedia existente');
centralAttachmentAssert(strpos($controller, 'public function enviarMidiaAjax()') !== false, 'controller deve expor endpoint AJAX de mídia');
centralAttachmentAssert(strpos($controller, '$this->validarCsrfAjax()') !== false, 'endpoint deve validar CSRF');
centralAttachmentAssert(strpos($controller, 'buscarAcessivel') !== false, 'endpoint deve validar acesso à conversa');
centralAttachmentAssert(strpos($controller, 'uploadMensagemMedia($arquivo, $tipoUpload)') !== false, 'controller deve usar serviço compartilhado de mídia');
centralAttachmentAssert(strpos($controller, "'media_id'=>\$media['media_id']") !== false, 'mensagem deve persistir media_id');
centralAttachmentAssert(strpos($controller, "'media_mime_type'=>\$media['mime']") !== false, 'mensagem deve persistir MIME');
centralAttachmentAssert(strpos($controller, "'media_nome'=>\$media['nome_original']") !== false, 'mensagem deve persistir nome original');

centralAttachmentAssert(strpos($painel, 'id="btnAnexarMensagem"') !== false, 'composer deve ter botão de anexo');
centralAttachmentAssert(strpos($painel, 'id="arquivoMensagem"') !== false, 'composer deve ter input de arquivo');
centralAttachmentAssert(strpos($painel, 'data-action-midia=') !== false, 'form deve conhecer endpoint de mídia');
centralAttachmentAssert(strpos($index, 'new FormData') !== false, 'frontend deve enviar arquivo via FormData');
centralAttachmentAssert(strpos($index, "form.data('action-midia')") !== false, 'frontend deve usar endpoint dedicado');

echo "Central attachment static tests passed\n";
