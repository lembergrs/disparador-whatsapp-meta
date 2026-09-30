<?php
namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\ParceiroApi;

class ParceiroAdminController extends Controller
{
    private $model;

    public function __construct()
    {
        Auth::admin();
        $this->model=new ParceiroApi();
    }

    public function index()
    {
        $this->view('parceiros/index',[
            'titulo'=>'Parceiros API',
            'parceiros'=>$this->model->listarAdmin(),
            'clientes'=>$this->model->listarClientesDisponiveisAdmin()
        ]);
    }

    public function detalhe()
    {
        $id=(int)($_GET['id'] ?? 0);
        $parceiro=$this->model->buscarAdmin($id);
        if(!$parceiro){ Session::flash('error','Parceiro não encontrado.'); $this->redirect('parceiroAdmin'); }

        $clientes=array_values(array_filter($this->model->listarClientesDisponiveisAdmin(),function($c){
            return ($c['CLI_TipoConta'] ?? '') === 'cliente_partner_vinculado';
        }));

        $contas=[];
        foreach($clientes as $cliente){
            $contas[(int)$cliente['CLI_ID']]=$this->model->listarContasClienteAdmin($cliente['CLI_ID']);
        }

        $this->view('parceiros/detalhe',[
            'titulo'=>'Parceiro API',
            'parceiro'=>$parceiro,
            'canais'=>$this->model->listarCanaisAutorizados($id),
            'chaves'=>$this->model->listarChavesAdmin($id),
            'clientes'=>$clientes,
            'contasPorCliente'=>$contas,
            'novaApiKey'=>Session::get('partner_api_key_once')
        ]);
    }

    public function salvar()
    {
        $this->validarCsrfPost();
        $clienteId=(int)($_POST['cliente_id'] ?? 0);
        $nome=trim($_POST['nome'] ?? '');
        $identificador=strtolower(trim($_POST['identificador'] ?? ''));
        $webhook=trim($_POST['webhook_url'] ?? '');

        if(!$clienteId || $nome==='' || !preg_match('/^[a-z0-9_-]{2,80}$/',$identificador)){
            Session::flash('error','Informe cliente partner, nome e identificador válido.');
            $this->redirect('parceiroAdmin');
        }
        $cliente=null;
        foreach($this->model->listarClientesDisponiveisAdmin() as $item){
            if((int)$item['CLI_ID']===$clienteId && $item['CLI_TipoConta']==='cliente_partner'){ $cliente=$item; break; }
        }
        if(!$cliente){ Session::flash('error','O cadastro selecionado não é cliente_partner.'); $this->redirect('parceiroAdmin'); }
        if($webhook!=='' && !filter_var($webhook,FILTER_VALIDATE_URL)){
            Session::flash('error','URL de webhook inválida.'); $this->redirect('parceiroAdmin');
        }

        try{
            $id=$this->model->salvarParceiroAdmin($clienteId,$nome,$identificador,$webhook);
            Session::flash('success','Parceiro cadastrado.');
            $this->redirect('parceiroAdmin/detalhe&id='.$id);
        }catch(\Throwable $e){
            Session::flash('error','Não foi possível cadastrar o parceiro. Verifique se o cliente/identificador já está em uso.');
            $this->redirect('parceiroAdmin');
        }
    }

    public function aprovar()
    {
        $this->validarCsrfPost();
        $parceiroId=(int)($_POST['parceiro_id'] ?? 0);
        if(!$this->model->buscarAdmin($parceiroId)){ Session::flash('error','Parceiro não encontrado.'); $this->redirect('parceiroAdmin'); }
        $this->model->aprovarAdmin($parceiroId);
        Session::flash('success','Cadastro Partner aprovado. A validação/onboarding e a cobrança de implantação podem seguir pelas próximas etapas.');
        $this->redirect('parceiroAdmin/detalhe&id='.$parceiroId);
    }

    public function vincular()
    {
        $this->validarCsrfPost();
        $parceiroId=(int)($_POST['parceiro_id'] ?? 0);
        try{
            $this->model->vincularCanalAdmin($parceiroId,(int)($_POST['cliente_id'] ?? 0),(int)($_POST['meta_id'] ?? 0),trim($_POST['identificador_externo'] ?? ''));
            Session::flash('success','Cliente/número vinculado ao parceiro.');
        }catch(\Throwable $e){
            Session::flash('error',$e->getMessage());
        }
        $this->redirect('parceiroAdmin/detalhe&id='.$parceiroId);
    }

    public function desvincular()
    {
        $this->validarCsrfPost();
        $parceiroId=(int)($_POST['parceiro_id'] ?? 0);
        $this->model->inativarVinculoAdmin($parceiroId,(int)($_POST['vinculo_id'] ?? 0));
        Session::flash('success','Vínculo inativado.');
        $this->redirect('parceiroAdmin/detalhe&id='.$parceiroId);
    }

    public function gerarChave()
    {
        $this->validarCsrfPost();
        $parceiroId=(int)($_POST['parceiro_id'] ?? 0);
        $nome=trim($_POST['nome'] ?? 'Integração');
        $segredo=$this->model->gerarChaveAdmin($parceiroId,$nome ?: 'Integração');
        Session::flash('partner_api_key_once',$segredo);
        Session::flash('success','API key gerada. Copie agora: ela não será exibida novamente.');
        $this->redirect('parceiroAdmin/detalhe&id='.$parceiroId);
    }

    public function revogarChave()
    {
        $this->validarCsrfPost();
        $parceiroId=(int)($_POST['parceiro_id'] ?? 0);
        $this->model->revogarChaveAdmin($parceiroId,(int)($_POST['chave_id'] ?? 0));
        Session::flash('success','API key revogada.');
        $this->redirect('parceiroAdmin/detalhe&id='.$parceiroId);
    }
}
