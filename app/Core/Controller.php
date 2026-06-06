<?php

namespace Core;

class Controller
{
    protected function view(
        $view,
        $dados = [],
        $layout = true
    ){

        extract($dados);

        $viewPath =
            __DIR__ . "/../Views/{$view}.php";

        if($layout){

            require __DIR__ . "/../Views/layouts/master.php";

        }else{

            require $viewPath;

        }

    }

    protected function redirect($url)
    {
        header(
            "Location: " .
            BASE_URL .
            "/index.php?url=" .
            $url
        );

        exit;
    }
}