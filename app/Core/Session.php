<?php

namespace Core;

class Session
{
    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function get($key)
    {
        return $_SESSION[$key] ?? null;
    }

    public static function has($key)
    {
        return isset($_SESSION[$key]);
    }

    public static function remove($key)
    {
        unset($_SESSION[$key]);
    }

    public static function flash($type, $message)
    {
        $_SESSION['flash'] = [

            'type' => $type,
            'message' => $message

        ];
    }

    public static function getFlash()
    {
        if(isset($_SESSION['flash'])){

            $flash = $_SESSION['flash'];

            unset($_SESSION['flash']);

            return $flash;
        }

        return null;
    }
}