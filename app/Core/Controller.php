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

    protected function validarCsrfPost()
    {
        Csrf::exigirPost();
    }

    protected function viewComLayout($view, $layout, array $dados = [])
    {
        extract($dados);
        $viewPath = __DIR__ . "/../Views/{$view}.php";
        ob_start();
        require $viewPath;
        $conteudo = ob_get_clean();
        require __DIR__ . "/../Views/{$layout}.php";
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
