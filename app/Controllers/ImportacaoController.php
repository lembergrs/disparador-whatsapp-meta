<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Upload;
use Core\Spreadsheet;

use Models\Contato;

class ImportacaoController
extends Controller
{
    private $contatoModel;

    public function __construct()
    {
        Auth::check();

        $this->contatoModel =
            new Contato();
    }

    public function index()
    {
        $usuario =
            Auth::usuario();

        $contatos =
            $this->contatoModel
            ->listarPorCliente(
                $usuario['cliente_id']
            );

        $this->view(
            'importacao/index',
            [
                'titulo' => 'Importação',
                'contatos' => $contatos
            ]
        );
    }

    public function importar()
    {
        try{

            $usuario =
                Auth::usuario();

            $arquivo =
                Upload::arquivo(
                    $_FILES['arquivo']
                );

            $linhas =
                Spreadsheet::ler(
                    $arquivo
                );

            $importados = 0;

            $cabecalho = $linhas[0];

            foreach($linhas as $index => $linha){

                if($index == 0){
                    continue;
                }

                $nome = trim(
                    $linha[0] ?? ''
                );

                $telefone = trim(
                    $linha[1] ?? ''
                );

                if(empty($telefone)){
                    continue;
                }

                $telefone =
                    preg_replace(
                        '/[^0-9]/',
                        '',
                        $telefone
                    );

                if(
                    strlen($telefone) < 10
                ){
                    continue;
                }

                $existe =
                    $this->contatoModel
                    ->telefoneExiste(
                        $usuario['cliente_id'],
                        $telefone
                    );

                if($existe){
                    continue;
                }

                $dadosContato = [];

                foreach($cabecalho as $coluna => $nomeCampo){

                    $dadosContato[$nomeCampo] =
                        $linha[$coluna] ?? '';

                }

                $this->contatoModel->salvar([
                    'cliente_id' => $usuario['cliente_id'],
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'dados_json' => json_encode(
                        $dadosContato,
                        JSON_UNESCAPED_UNICODE
                    )
                ]);

                $importados++;
            }

            $_SESSION['sucesso'] =
                "{$importados} contatos importados.";

        }catch(\Exception $e){

            $_SESSION['erro'] =
                $e->getMessage();

        }

        $this->redirect(
            'importacao'
        );
    }
}