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
}
