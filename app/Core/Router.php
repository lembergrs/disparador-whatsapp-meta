<?php

namespace Core;

class Router
{
    public function dispatch()
    {
        $url = $_GET['url'] ?? 'site';

        // Rotas públicas versionadas da Partner API.
        if(preg_match('#^api/v1(?:/([^/]+))?/?$#', trim((string)$url, '/'), $apiRoute)){
            $url = 'apiV1/' . (!empty($apiRoute[1]) ? $apiRoute[1] : 'status');
        }

        if($url === 'whatsapp-business'){
            $url = 'site/whatsappBusiness';
        }
        if($url === 'limites-whatsapp'){
            $url = 'site/limitesWhatsapp';
        }
        if($url === 'precos-whatsapp-meta'){
            $url = 'site/precosWhatsappMeta';
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
