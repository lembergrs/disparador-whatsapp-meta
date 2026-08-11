<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Services\Indicacao\IndicacaoClienteReadService;

class IndicacaoController extends Controller
{
    public function index()
    {
        Auth::cliente();
        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $dados = (new IndicacaoClienteReadService())->obterParaCliente($clienteId);

        if(!empty($dados['compartilhamento']['disponivel'])){
            $codigo = rawurlencode((string) $dados['compartilhamento']['codigo']);
            $dados['compartilhamento']['link'] = rtrim(BASE_URL, '/') . '/index.php?url=site/cadastro&ref=' . $codigo;
        }

        $this->view('indicacao/index', [
            'titulo'=>'Indique e Ganhe',
            'indicacao'=>$dados
        ]);
    }
}
