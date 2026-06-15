<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Models\MetaConta;

class ConfiguracaoController extends Controller
{
    private $metaContaModel;

    public function __construct()
    {
        Auth::clienteAdmin();

        $this->metaContaModel =
            new MetaConta();
    }

    public function meta()
    {
        $usuario =
            Auth::usuario();

        $contas =
            $this->metaContaModel
            ->listarPorCliente(
                $usuario['CLI_ID']
            );

        $limiteNumeros =
            $this->metaContaModel
            ->avaliarLimiteNumerosPorCliente(
                $usuario['CLI_ID']
            );

        $this->view(
            'configuracao/meta',
            [
                'titulo' => 'Números WhatsApp',
                'contas' => $contas,
                'limiteNumeros' => $limiteNumeros
            ]
        );
    }
}
