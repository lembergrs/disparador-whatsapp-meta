<?php

namespace Controllers;

use Core\Controller;

class SiteController extends Controller
{
    public function index()
    {
        $this->view(
            'site/home',
            [
                'titulo' => 'Disparador WhatsApp'
            ],
            false
        );
    }

    public function cadastro()
    {
        $this->view(
            'site/cadastro',
            [
                'titulo' => 'Cadastro'
            ],
            false
        );
    }
}