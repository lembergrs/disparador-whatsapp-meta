<?php

$arquivo = file_get_contents(dirname(__DIR__) . '/app/Models/Conversa.php');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($arquivo, "'audio' => '🎤 Áudio'") !== false, 'Áudio deve ter resumo amigável.');
$assert(strpos($arquivo, "'image' => '📷 Imagem'") !== false, 'Imagem deve ter resumo amigável.');
$assert(strpos($arquivo, "'document' => '📄 Documento'") !== false, 'Documento deve ter resumo amigável.');
$assert(strpos($arquivo, "'video' => '🎥 Vídeo'") !== false, 'Vídeo deve ter resumo amigável.');
$assert(strpos($arquivo, "'sticker' => 'Sticker'") !== false, 'Sticker deve ter resumo amigável.');
$assert(strpos($arquivo, "if(\$tipo === 'reaction'){\n            return null;") !== false, 'Reaction não deve substituir o resumo da conversa.');
$assert(strpos($arquivo, "'image', 'document', 'video'") !== false, 'Tipos com caption devem preservar texto no resumo.');

echo "Conversation summary checks passed\n";
