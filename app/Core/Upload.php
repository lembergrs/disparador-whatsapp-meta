<?php

namespace Core;

class Upload
{
    public static function arquivo($arquivo)
    {
        if(
            !isset($arquivo['tmp_name'])
        ){
            throw new \Exception(
                'Arquivo não enviado'
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
            'xlsx'
        ];

        if(
            !in_array(
                $extensao,
                $permitidos
            )
        ){
            throw new \Exception(
                'Arquivo inválido'
            );
        }

        $nome =
            time() .
            '_' .
            uniqid() .
            '.' .
            $extensao;

        $diretorio =
            '../public/uploads/';

        if(!is_dir($diretorio)){
            mkdir(
                $diretorio,
                0777,
                true
            );
        }

        $caminho =
            $diretorio .
            $nome;

        move_uploaded_file(
            $arquivo['tmp_name'],
            $caminho
        );

        return $caminho;
    }
}