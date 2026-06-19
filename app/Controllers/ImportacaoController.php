<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Upload;
use Core\Spreadsheet;
use Core\Session;

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

        $listaSelecionadaDados = null;

        if($listaSelecionada !== ''){

            $listaSelecionadaDados =
                $this->listaModel->buscar(
                    $listaSelecionada,
                    $usuario['cliente_id']
                );

            if(!$listaSelecionadaDados){
                $listaSelecionada = '';
            }
        }

        $this->view(
            'importacao/index',
            [
                'titulo' => 'Importação',
                'contatos' => $contatos,
                'listas' => $listas,
                'listaSelecionada' => $listaSelecionada,
                'listaSelecionadaDados' => $listaSelecionadaDados
            ]
        );
    }

    public function importar()
    {
        $this->validarCsrfPost();

        $arquivo = null;
        $listaId = $_POST['lista_id'] ?? '';

        try{

            $usuario = Auth::usuario();

            $clienteId = $usuario['cliente_id'];

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

                $lista =
                    $this->listaModel->buscar(
                        $listaId,
                        $clienteId
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

            $nomeListaImportada =
                $lista['LST_Nome']
                ?? 'selecionada';

            Session::flash(
                'success',
                "{$vinculados} contatos importados com sucesso para a lista {$nomeListaImportada}. "
                . "{$importados} novo(s) contato(s) criado(s). "
                . "{$ignorados} linha(s) ignorada(s)."
            );

            $this->redirect(
                'listaContato/visualizar&id='
                . $listaId
            );

            return;

        }catch(\Exception $e){

            if(!empty($arquivo) && is_file($arquivo)){
                unlink($arquivo);
            }

            Session::flash(
                'error',
                $e->getMessage()
            );

        }

        $redirect = 'importacao';

        if(!empty($listaId) && $listaId !== 'nova'){
            $redirect .= '&lista=' . urlencode($listaId);
        }

        $this->redirect($redirect);
    }
}
