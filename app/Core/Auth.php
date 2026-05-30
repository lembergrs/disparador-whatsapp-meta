<?php

namespace Core;

class Auth
{
    public static function check()
    {
        if(!isset($_SESSION['usuario'])){

            header(
                "Location: " .
                BASE_URL .
                "/login"
            );

            exit;
        }
    }

    public static function admin()
    {
        self::check();

        if(
            $_SESSION['usuario']['nivel']
            != 'admin'
        ){
            die('Acesso negado');
        }
    }

    public static function usuario()
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function logout()
    {
        session_destroy();
    }

    public static function cliente()
    {
        if(
            !isset($_SESSION['usuario'])
            ||
            $_SESSION['usuario']['nivel']
            != 'cliente'
        ){

            die('Acesso negado');

        }
    }
}