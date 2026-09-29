<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ConversaController.php');
$model = file_get_contents($root . '/app/Models/Conversa.php');
$service = file_get_contents($root . '/app/Services/MetaMediaService.php');
$view = file_get_contents($root . '/app/Views/conversas/partials/mensagens.php');

$assert(strpos($model, 'buscarMensagemAcessivel') !== false, 'Mídia deve ser resolvida por mensagem com escopo do usuário.');
$assert(strpos($controller, 'buscarMensagemAcessivel') !== false, 'Endpoint não pode servir mídia sem validar a mensagem.');
$assert(strpos($controller, "['image', 'video', 'document', 'audio', 'sticker']") !== false, 'Endpoint deve limitar tipos de mídia permitidos.');
$assert(strpos($service, 'obterMidiaMensagem') !== false, 'Serviço deve recuperar mídia da Meta.');
$assert(strpos($service, 'storage/cache/meta_media/') !== false, 'Mídia deve usar cache privado fora de public.');
$assert(strpos($service, 'Authorization: Bearer ') !== false, 'Download da Meta deve ser autenticado.');
$assert(strpos($controller, 'X-Content-Type-Options: nosniff') !== false, 'Resposta de mídia deve impedir MIME sniffing.');
$assert(strpos($view, '<audio controls') !== false, 'Central deve renderizar player de áudio.');
$assert(strpos($view, '<img src=') !== false, 'Central deve renderizar imagem/sticker.');
$assert(strpos($view, '<video controls') !== false, 'Central deve renderizar vídeo.');
$assert(strpos($view, 'fa-file-alt') !== false, 'Central deve renderizar documento.');
$assert(strpos($view, 'MTA_Token') === false, 'View nunca deve receber token da Meta.');

echo "Conversation media delivery checks passed\n";
