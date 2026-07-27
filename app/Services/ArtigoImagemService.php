<?php

namespace Services;

class ArtigoImagemService
{
    public static function salvar(array $arquivo)
    {
        if(($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE){
            return null;
        }
        if(($arquivo['error'] ?? null) !== UPLOAD_ERR_OK || ($arquivo['size'] ?? 0) <= 0 || $arquivo['size'] > 5 * 1024 * 1024){
            throw new \InvalidArgumentException('A imagem deve possuir no máximo 5 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        $extensoes = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        if(!isset($extensoes[$mime])){
            throw new \InvalidArgumentException('Envie uma imagem JPG, PNG ou WebP válida.');
        }
        if(@getimagesize($arquivo['tmp_name']) === false){
            throw new \InvalidArgumentException('O arquivo enviado não é uma imagem válida.');
        }
        $diretorio = dirname(__DIR__, 2) . '/public/uploads/blog';
        if(!is_dir($diretorio) && !mkdir($diretorio, 0770, true)){
            throw new \RuntimeException('Não foi possível preparar o diretório de imagens.');
        }
        $nome = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
        if(!move_uploaded_file($arquivo['tmp_name'], $diretorio . '/' . $nome)){
            throw new \RuntimeException('Não foi possível salvar a imagem.');
        }
        return '/public/uploads/blog/' . $nome;
    }
}
