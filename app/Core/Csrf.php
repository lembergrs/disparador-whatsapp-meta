<?php

namespace Core;

class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function token()
    {
        if(empty($_SESSION[self::SESSION_KEY])){
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function validar($token)
    {
        return is_string($token)
            && isset($_SESSION[self::SESSION_KEY])
            && hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    public static function input()
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    public static function validarPost()
    {
        return self::validar($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    }

    public static function exigirPost()
    {
        if(!self::validarPost()){
            http_response_code(403);
            exit('Token de segurança inválido.');
        }
    }
}
