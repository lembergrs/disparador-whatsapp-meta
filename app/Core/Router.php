<?php

namespace Core;

class Router
{
    public function dispatch()
    {
        $url = $_GET['url'] ?? 'site';

        if($url === 'whatsapp-business'){
            $url = 'site/whatsappBusiness';
        }

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
            $controllerClass === 'Controllers\\BlogController'
            && !in_array($method, ['index', 'categoria'], true)
        ){
            $controller->artigo($method);
            return;
        }

        if(!method_exists($controller, $method)){
            die('Método não encontrado');
        }

        $controller->$method();
    }
}
