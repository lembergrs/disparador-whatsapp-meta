<?php
namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\ParceiroConvite;

class ParceiroClientesController extends Controller
{
    private $model;
    public function __construct(){ Auth::clienteAdmin(); $this->model=new ParceiroConvite(); }

    private function parceiro()
    {
        $u=Auth::usuario();
        $p=$this->model->parceiroPorCliente((int)($u['CLI_ID']??0));
        if(!$p){ http_response_code(403); die('Acesso negado'); }
        return $p;
    }

    public function index()
    {
        $p=$this->parceiro();
        $this->view('parceiros/clientes_partner',['titulo'=>'Clientes Partner','parceiro'=>$p,'convites'=>$this->model->listar($p['PAR_ID']),'clientesPartner'=>$this->model->listarClientes($p['PAR_ID']),'novoConvite'=>Session::get('partner_invite_url_once')]);
        Session::remove('partner_invite_url_once');
    }

    public function gerarConvite()
    {
        $this->validarCsrfPost();
        $p=$this->parceiro();
        $token=$this->model->gerar((int)$p['PAR_ID'],trim((string)($_POST['nome_referencia']??'')));
        Session::set('partner_invite_url_once',rtrim(BASE_URL,'/').'/index.php?url=parceiroConvite&token='.rawurlencode($token));
        Session::flash('success','Convite criado. Copie o link e envie ao cliente.');
        $this->redirect('parceiroClientes');
    }
}
