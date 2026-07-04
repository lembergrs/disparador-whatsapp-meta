<?php

namespace Helpers;

class UrlHelper
{
    public static function publicUrl($caminho)
    {
        $caminho = trim((string) $caminho);

        if($caminho === ''){
            return '';
        }

        if(preg_match('/^https?:\/\//i', $caminho)){
            return $caminho;
        }

        $baseUrl = rtrim((string) BASE_URL, '/');

        return $baseUrl . '/' . ltrim($caminho, '/');
    }

    public static function uploadPublicPath($subpasta, $arquivo)
    {
        $base = defined('UPLOADS_PUBLIC_PATH')
            ? rtrim((string) UPLOADS_PUBLIC_PATH, '/')
            : '/uploads';

        if($base === ''){
            $base = '/uploads';
        }

        if($base[0] !== '/'){
            $base = '/' . $base;
        }

        $subpasta = trim((string) $subpasta, '/');
        $arquivo = ltrim((string) $arquivo, '/');

        $partes = [$base];

        if($subpasta !== ''){
            $partes[] = $subpasta;
        }

        if($arquivo !== ''){
            $partes[] = $arquivo;
        }

        return implode('/', $partes);
    }
}
