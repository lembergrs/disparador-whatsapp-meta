<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;

use Models\ListaContato;
use Models\ListaContatoItem;
use Models\Contato;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ListaContatoController extends Controller
{
    private $listaModel;
    private $listaItemModel;
    private $contatoModel;

    public function __construct()
    {
        Auth::check();

        $this->listaModel =
            new ListaContato();

        $this->listaItemModel =
            new ListaContatoItem();
        
        $this->contatoModel = 
            new Contato();
    }

    public function index()
    {
        $usuario =
            Auth::usuario();

        $listas =
            $this->listaModel
            ->listarPorCliente(
                $usuario['cliente_id']
            );

        $this->view(
            'listas/index',
            [
                'titulo' => 'Listas de Contatos',
                'listas' => $listas
            ]
        );
    }

    public function baixarModelo()
    {
        $spreadsheet = new Spreadsheet();

        $abaContatos = $spreadsheet->getActiveSheet();
        $abaContatos->setTitle('Contatos');
        $abaContatos->fromArray(
            [
                ['Nome', 'Telefone', 'Cidade', 'Empresa', 'Email', 'Observacao'],
                ['João da Silva', '(41) 99999-9999', 'Curitiba', 'Empresa A', 'joao@email.com', 'Cliente interessado'],
                ['Maria Oliveira', '41988888888', 'São José dos Pinhais', 'Empresa B', 'maria@email.com', 'Cliente recorrente'],
                ['Pedro Santos', '5541977777777', 'Colombo', 'Empresa C', 'pedro@email.com', 'Enviar promoção']
            ]
        );

        $abaInstrucoes = $spreadsheet->createSheet();
        $abaInstrucoes->setTitle('Instruções');
        $abaInstrucoes->fromArray(
            [
                ['Instruções'],
                ['A primeira linha deve conter os nomes das colunas.'],
                ['A coluna Telefone é obrigatória.'],
                ['O sistema aceita telefone com máscara, sem máscara ou com código do país 55.'],
                ['Não é necessário informar o código do país.'],
                ['Uma linha por contato.'],
                ['As demais colunas são opcionais.'],
                ['Colunas extras poderão ser utilizadas como variáveis em templates e campanhas.'],
                ['Salve sempre em formato XLSX antes de importar.']
            ]
        );

        foreach([$abaContatos, $abaInstrucoes] as $aba){
            foreach(range('A', 'F') as $coluna){
                $aba->getColumnDimension($coluna)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        if(ob_get_length()){
            ob_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="modelo_lista_contatos.xlsx"');
        header('Cache-Control: max-age=0, must-revalidate');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function visualizar()
    {
        $usuario =
            Auth::usuario();

        $id =
            $_POST['id']
            ?? $_GET['id']
            ?? null;

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $lista =
            $this->listaModel
            ->buscar(
                $id,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $contatos =
            $this->listaItemModel
            ->listarContatos(
                $id
            );

        $this->view(
            'listas/visualizar',
            [
                'titulo' => $lista['LST_Nome'],
                'lista' => $lista,
                'contatos' => $contatos
            ]
        );
    }

    public function criar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $nome =
            trim(
                $_POST['nome']
                ?? ''
            );

        if($nome == ''){

            Session::flash(
                'error',
                'Informe o nome da lista.'
            );

            $this->redirect('listaContato');

            return;
        }

        $listaId =
            $this->listaModel
            ->criar(
                $usuario['cliente_id'],
                $nome
            );

        Session::flash(
            'success',
            'Lista criada com sucesso.'
        );

        $this->redirect(
            'listaContato/visualizar&id='
            . $listaId
        );
    }

    public function salvarEdicao()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $id =
            $_POST['id']
            ?? null;

        $nome =
            trim(
                $_POST['nome']
                ?? ''
            );

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect('listaContato');

            return;
        }

        if($nome == ''){

            Session::flash(
                'error',
                'Informe o nome da lista.'
            );

            $this->redirect('listaContato');

            return;
        }

        $lista =
            $this->listaModel
            ->buscar(
                $id,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $this->listaModel
            ->atualizar(
                $id,
                $usuario['cliente_id'],
                $nome
            );

        Session::flash(
            'success',
            'Lista atualizada com sucesso.'
        );

        $this->redirect(
            'listaContato'
        );
    }

    public function inativar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $id =
            $_POST['id']
            ?? null;

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $lista =
            $this->listaModel
            ->buscar(
                $id,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $this->listaModel
            ->inativar(
                $id,
                $usuario['cliente_id']
            );

        Session::flash(
            'success',
            'Lista inativada com sucesso.'
        );

        $this->redirect(
            'listaContato'
        );
    }

    private function normalizarTelefone($telefone)
    {
        $telefone =
            preg_replace('/\D/', '', $telefone);

        if(substr($telefone, 0, 2) != '55'){
            $telefone =
                '55' . $telefone;
        }

        return $telefone;
    }

    public function removerContato()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $listaId =
            $_POST['lista'] ?? null;

        $contatoId =
            $_POST['contato'] ?? null;

        if(!$listaId || !$contatoId){

            Session::flash(
                'error',
                'Dados inválidos.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $lista =
            $this->listaModel->buscar(
                $listaId,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $this->listaItemModel
            ->removerContato(
                $listaId,
                $contatoId
            );

        Session::flash(
            'success',
            'Contato removido da lista.'
        );

        $this->redirect(
            'listaContato/visualizar&id='
            . $listaId
        );
    }

    public function adicionarContato()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $listaId =
            $_POST['lista_id'] ?? null;

        $nome =
            trim($_POST['nome'] ?? '');

        $telefone =
            trim($_POST['telefone'] ?? '');

        if(
            !$listaId
            ||
            $nome == ''
            ||
            $telefone == ''
        ){

            Session::flash(
                'error',
                'Preencha todos os campos.'
            );

            $this->redirect(
                'listaContato/visualizar&id='
                . $listaId
            );

            return;
        }

        $lista =
            $this->listaModel->buscar(
                $listaId,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $telefone =
            $this->normalizarTelefone(
                $telefone
            );

        $contato =
            $this->contatoModel
            ->telefoneExiste(
                $usuario['cliente_id'],
                $telefone
            );

        if($contato){

            $contatoId =
                $contato['CON_ID'];

        }else{

            $contatoId =
                $this->contatoModel
                ->salvar([

                    'cliente_id' =>
                        $usuario['cliente_id'],

                    'nome' =>
                        $nome,

                    'telefone' =>
                        $telefone,

                    'dados_json' =>
                        null

                ]);
        }

        $this->listaItemModel
            ->adicionar(
                $listaId,
                $contatoId
            );

        Session::flash(
            'success',
            'Contato adicionado com sucesso.'
        );

        $this->redirect(
            'listaContato/visualizar&id='
            . $listaId
        );
    }

    public function duplicar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $id =
            $_POST['id'] ?? null;

        if(!$id){

            Session::flash(
                'error',
                'Lista não informada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $lista =
            $this->listaModel->buscar(
                $id,
                $usuario['cliente_id']
            );

        if(!$lista){

            Session::flash(
                'error',
                'Lista não encontrada.'
            );

            $this->redirect(
                'listaContato'
            );

            return;
        }

        $novaListaId =
            $this->listaModel->duplicar(
                $id,
                $usuario['cliente_id']
            );

        $contatos =
            $this->listaItemModel
            ->listarIdsDaLista($id);

        foreach($contatos as $contato){

            $this->listaItemModel
                ->adicionar(
                    $novaListaId,
                    $contato['CON_ID']
                );

        }

        Session::flash(
            'success',
            'Lista duplicada com sucesso.'
        );

        $this->redirect(
            'listaContato'
        );
    }

}
