<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Upload;
use Core\Spreadsheet;

use Models\Contato;
use Models\ListaContato;
use Models\ListaContatoItem;

class ImportacaoController extends Controller
{
    private $contatoModel;
    private $listaModel;
    private $listaItemModel;

    public function __construct()
    {
        Auth::check();

        $this->contatoModel = new Contato();
        $this->listaModel = new ListaContato();
        $this->listaItemModel = new ListaContatoItem();
    }

    public function index()
    {
        $usuario = Auth::usuario();

        $contatos =
            $this->contatoModel->listarPorCliente(
                $usuario['cliente_id']
            );

        $listas =
            $this->listaModel->listarPorCliente(
                $usuario['cliente_id']
            );

        $listaSelecionada =
            $_GET['lista']
            ?? '';

        $this->view(
            'importacao/index',
            [
                'titulo' => 'Importação',
                'contatos' => $contatos,
                'listas' => $listas,
                'listaSelecionada' => $listaSelecionada
            ]
        );
    }

    public function importar()
    {
        $this->validarCsrfPost();

        try{

            $usuario = Auth::usuario();

            $clienteId = $usuario['cliente_id'];

            $listaId = $_POST['lista_id'] ?? '';

            if($listaId == 'nova'){

                $nomeLista = trim($_POST['nova_lista'] ?? '');

                if($nomeLista == ''){
                    throw new \Exception('Informe o nome da nova lista.');
                }

                $listaId =
                    $this->listaModel->criar(
                        $clienteId,
                        $nomeLista
                    );

            }else{

                if(empty($listaId)){
                    throw new \Exception('Selecione uma lista de contatos.');
                }

                $lista =
                    $this->listaModel->buscar(
                        $listaId,
                        $clienteId
                    );

                if(!$lista){
                    throw new \Exception('Lista inválida.');
                }
            }

            $arquivo =
                Upload::arquivo(
                    $_FILES['arquivo']
                );

            $linhas =
                Spreadsheet::ler(
                    $arquivo
                );

            if(empty($linhas[0])){
                throw new \Exception('Arquivo sem cabeçalho.');
            }

            $cabecalho = $linhas[0];

            $importados = 0;
            $vinculados = 0;
            $ignorados = 0;

            foreach($linhas as $index => $linha){

                if($index == 0){
                    continue;
                }

                $nome = trim($linha[0] ?? '');
                $telefone = trim($linha[1] ?? '');

                if(empty($telefone)){
                    $ignorados++;
                    continue;
                }

                $telefone =
                    preg_replace(
                        '/[^0-9]/',
                        '',
                        $telefone
                    );

                if(strlen($telefone) < 10){
                    $ignorados++;
                    continue;
                }

                if(substr($telefone, 0, 2) != '55'){
                    $telefone = '55' . $telefone;
                }

                $dadosContato = [];

                foreach($cabecalho as $coluna => $nomeCampo){

                    $nomeCampo = trim($nomeCampo);

                    if($nomeCampo == ''){
                        continue;
                    }

                    $dadosContato[$nomeCampo] =
                        $linha[$coluna] ?? '';
                }

                $dadosContato['Telefone'] = $telefone;

                $contatoExistente =
                    $this->contatoModel->buscarPorTelefone(
                        $clienteId,
                        $telefone
                    );

                if($contatoExistente){

                    $contatoId =
                        $contatoExistente['CON_ID'];

                }else{

                    $contatoId =
                        $this->contatoModel->salvar([
                            'cliente_id' => $clienteId,
                            'nome' => $nome,
                            'telefone' => $telefone,
                            'dados_json' => json_encode(
                                $dadosContato,
                                JSON_UNESCAPED_UNICODE
                            )
                        ]);

                    $importados++;
                }

                $this->listaItemModel->adicionar(
                    $listaId,
                    $contatoId
                );

                $vinculados++;
            }

            if(!empty($arquivo) && is_file($arquivo)){
                unlink($arquivo);
            }

            $_SESSION['sucesso'] =
                "{$importados} novo(s) contato(s) importado(s). "
                . "{$vinculados} contato(s) vinculado(s) à lista. "
                . "{$ignorados} linha(s) ignorada(s).";

        }catch(\Exception $e){

            if(!empty($arquivo) && is_file($arquivo)){
                unlink($arquivo);
            }

            $_SESSION['erro'] =
                $e->getMessage();

        }

        $this->redirect('importacao');
    }
}