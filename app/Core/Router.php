<?php

namespace Core;

class Router
{
    public function dispatch()
    {
        $url = $_GET['url'] ?? 'login';

        $url = explode('/', $url);

        $controller =
            $url[0] ?? 'login';

        $controllerName =
            ucfirst($controller) .
            'Controller';

        $method =
            $url[1] ?? 'index';

        $controllerClass =
            "Controllers\\{$controllerName}";

        if(!class_exists($controllerClass)){
            die('Controller não encontrado');
        }

        $controller =
            new $controllerClass();

        if(
            !method_exists(
                $controller,
                $method
            )
        ){
            die('Método não encontrado');
        }

        $controller->$method();
    }
}