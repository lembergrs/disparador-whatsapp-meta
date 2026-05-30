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
            "../app/Views/{$view}.php";

        if($layout){

            require "../app/Views/layouts/master.php";

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