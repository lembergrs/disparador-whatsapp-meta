<?php

namespace Core;

class Upload
{
    public static function arquivo($arquivo)
    {
        if(!isset($arquivo['tmp_name']) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
            throw new \Exception(
                'Arquivo não enviado'
            );
        }

        $tamanhoMaximo = 5 * 1024 * 1024;

        if(($arquivo['size'] ?? 0) <= 0 || $arquivo['size'] > $tamanhoMaximo){
            throw new \Exception(
                'Arquivo excede o tamanho máximo permitido.'
            );
        }

        $extensao = strtolower(
            pathinfo(
                $arquivo['name'],
                PATHINFO_EXTENSION
            )
        );

        $permitidos = [
            'xls',
            'xlsx',
            'vcf'
        ];

        if(
            !in_array(
                $extensao,
                $permitidos
            )
        ){
            throw new \Exception(
                'Arquivo inválido. Envie uma planilha XLS/XLSX ou um arquivo VCF.'
            );
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($arquivo['tmp_name']);

        $mimesPlanilha = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream'
        ];

        $mimesVcf = [
            'text/vcard',
            'text/x-vcard',
            'text/plain',
            'application/octet-stream'
        ];

        $mimesPermitidos = $extensao === 'vcf'
            ? $mimesVcf
            : $mimesPlanilha;

        if(!in_array($mime, $mimesPermitidos, true)){
            throw new \Exception(
                'Tipo de arquivo inválido'
            );
        }

        $nome =
            time() .
            '_' .
            uniqid() .
            '.' .
            $extensao;

        $diretorio =
            __DIR__ . '/../../storage/uploads/';

        if(!is_dir($diretorio)){
            mkdir(
                $diretorio,
                0770,
                true
            );
        }

        $caminho =
            $diretorio .
            $nome;

        if(!move_uploaded_file(
            $arquivo['tmp_name'],
            $caminho
        )){
            throw new \Exception('Não foi possível salvar o arquivo.');
        }

        return $caminho;
    }
}
