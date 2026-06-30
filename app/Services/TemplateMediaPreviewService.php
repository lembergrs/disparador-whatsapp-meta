<?php

namespace Services;

use Exception;

class TemplateMediaPreviewService
{
    private $limites = [
        'IMAGE' => 5 * 1024 * 1024,
        'VIDEO' => 16 * 1024 * 1024,
        'DOCUMENT' => 10 * 1024 * 1024,
    ];

    private $extensoes = [
        'IMAGE' => ['jpg', 'jpeg', 'png', 'webp'],
        'VIDEO' => ['mp4', '3gp', '3gpp'],
        'DOCUMENT' => ['pdf'],
    ];

    private $mimes = [
        'IMAGE' => ['image/jpeg', 'image/png', 'image/webp'],
        'VIDEO' => ['video/mp4', 'video/3gpp'],
        'DOCUMENT' => ['application/pdf'],
    ];

    public function salvarCopiaPreview(array $arquivo, $tipo)
    {
        $info = $this->validarArquivo($arquivo, $tipo);
        $dir = dirname(__DIR__, 2) . '/public/uploads/templates';

        if(!is_dir($dir)){
            mkdir($dir, 0770, true);
        }

        $this->garantirHtaccess($dir);

        $nomeFisico = date('YmdHis') . '_' . bin2hex(random_bytes(16)) . '.' . $info['extensao'];
        $destino = $dir . '/' . $nomeFisico;

        if(!copy($arquivo['tmp_name'], $destino)){
            throw new Exception('Não foi possível salvar a mídia de preview do template.');
        }

        chmod($destino, 0644);

        return [
            'url' => BASE_URL . '/public/uploads/templates/' . $nomeFisico,
            'path' => $destino,
            'nome_original' => $info['nome_original'],
            'mime' => $info['mime'],
            'tamanho' => $info['tamanho'],
            'tipo' => $info['tipo']
        ];
    }

    public function removerCopia($preview)
    {
        if(!is_array($preview) || empty($preview['path'])){
            return;
        }

        $base = realpath(dirname(__DIR__, 2) . '/public/uploads/templates');
        $arquivo = realpath($preview['path']);

        if($base && $arquivo && strpos($arquivo, $base) === 0 && is_file($arquivo)){
            @unlink($arquivo);
        }
    }

    private function validarArquivo(array $arquivo, $tipo)
    {
        $tipo = strtoupper((string) $tipo);

        if(!isset($this->limites[$tipo])){
            throw new Exception('Tipo de mídia inválido para preview.');
        }

        if(empty($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE){
            throw new Exception('Selecione um arquivo de mídia.');
        }

        if(($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK){
            throw new Exception('Falha ao receber o arquivo de mídia.');
        }

        $nomeOriginal = $this->nomeSeguro($arquivo['name'] ?? 'arquivo');
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        if(!in_array($ext, $this->extensoes[$tipo], true)){
            throw new Exception('Formato de arquivo não permitido para preview.');
        }

        $tamanho = (int) ($arquivo['size'] ?? 0);
        if($tamanho <= 0 || $tamanho > $this->limites[$tipo]){
            throw new Exception('Arquivo maior que o limite permitido para preview.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($arquivo['tmp_name']);

        if(!in_array($mime, $this->mimes[$tipo], true)){
            throw new Exception('MIME type inválido para preview.');
        }

        return [
            'tipo' => $tipo,
            'nome_original' => $nomeOriginal,
            'extensao' => $ext,
            'mime' => $mime,
            'tamanho' => $tamanho
        ];
    }

    private function nomeSeguro($nome)
    {
        $nome = basename((string) $nome);
        $nome = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nome);
        return trim($nome, '._') ?: 'arquivo';
    }

    private function garantirHtaccess($dir)
    {
        $arquivo = rtrim($dir, '/') . '/.htaccess';

        if(is_file($arquivo)){
            return;
        }

        file_put_contents($arquivo, "Options -Indexes\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phar\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phar)$\">\n    Require all denied\n</FilesMatch>\n");
    }
}
