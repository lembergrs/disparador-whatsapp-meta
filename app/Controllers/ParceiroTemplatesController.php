<?php
namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\ParceiroConvite;
use Models\TemplateMeta;
use Services\MetaService;
use Services\TemplateMediaPreviewService;

class ParceiroTemplatesController extends Controller
{
    private $parceiros;
    private $templates;

    public function __construct()
    {
        Auth::clienteAdmin();
        $this->parceiros=new ParceiroConvite();
        $this->templates=new TemplateMeta();
    }

    private function contexto()
    {
        $u=Auth::usuario();
        $p=$this->parceiros->parceiroPorCliente((int)($u['CLI_ID']??0));
        if(!$p){ http_response_code(403); die('Acesso negado'); }
        $clienteId=(int)($_REQUEST['cliente_id']??0);
        $cliente=$this->parceiros->buscarClienteGerenciavel((int)$p['PAR_ID'],$clienteId);
        if(!$cliente){ http_response_code(403); die('Cliente não autorizado para este Partner.'); }
        return [$p,$cliente];
    }

    private function canal($p,$cliente,$metaId)
    {
        $canal=$this->parceiros->buscarCanalGerenciavel((int)$p['PAR_ID'],(int)$cliente['CLI_ID'],(int)$metaId);
        if(!$canal){ http_response_code(403); die('Canal não autorizado para este Partner.'); }
        return $canal;
    }

    private function voltar($clienteId)
    {
        $this->redirect('parceiroTemplates?cliente_id='.(int)$clienteId);
    }

    public function index()
    {
        list($p,$cliente)=$this->contexto();
        $canais=$this->parceiros->listarCanaisGerenciaveis((int)$p['PAR_ID'],(int)$cliente['CLI_ID']);
        $templates=[];
        foreach($canais as $canal){
            $templates=array_merge($templates,$this->templates->listarPorClienteConta((int)$cliente['CLI_ID'],(int)$canal['MTA_ID']));
        }
        usort($templates,function($a,$b){return (int)$b['TMP_ID'] <=> (int)$a['TMP_ID'];});
        $this->view('templates/index',['titulo'=>'Templates — '.$cliente['CLI_Nome'],'templates'=>$templates,'contas'=>$canais,'partnerContexto'=>true,'partnerCliente'=>$cliente]);
    }

    public function criar()
    {
        $this->validarCsrfPost();
        list($p,$cliente)=$this->contexto();
        $metaId=(int)($_POST['meta']??0);
        $this->canal($p,$cliente,$metaId);
        $preview=null;
        try{
            $header=strtoupper((string)($_POST['header_tipo']??''));
            if(in_array($header,['IMAGE','VIDEO','DOCUMENT'],true) && !empty($_FILES['header_media'])){
                $preview=(new TemplateMediaPreviewService())->salvarCopiaPreview($_FILES['header_media'],$header);
            }
            $response=(new MetaService($metaId,(int)$cliente['CLI_ID']))->criarTemplate($_POST);
            if(isset($response['id']) && !empty($response['template_local'])){
                $t=$response['template_local']; $t['id']=$response['id']; $t['status']=$response['status']??($t['status']??'PENDING');
                if($preview){$t['header_media_url_exemplo']=$preview['url'];$t['header_media_nome']=$preview['nome_original'];$t['header_media_tipo']=$preview['tipo'];}
                $this->templates->salvarOuAtualizar($metaId,$t);
                Session::flash('success','Template do cliente enviado para aprovação.');
            }else{
                if($preview){(new TemplateMediaPreviewService())->removerCopia($preview);}
                Session::flash('error','A Meta não aceitou a criação do template.');
            }
        }catch(\Throwable $e){
            if($preview){(new TemplateMediaPreviewService())->removerCopia($preview);}
            Session::flash('error',$e->getMessage());
        }
        $this->voltar((int)$cliente['CLI_ID']);
    }

    public function editar()
    {
        $this->validarCsrfPost();
        list($p,$cliente)=$this->contexto();
        $id=(int)($_POST['id']??0);
        $t=$this->templates->buscarPorCliente($id,(int)$cliente['CLI_ID']);
        if(!$t){http_response_code(403);die('Template não autorizado para este Partner.');}
        $this->canal($p,$cliente,(int)$t['MTA_ID']);
        Session::flash('error','Templates aprovados pela Meta podem exigir criação de um novo template para alteração.');
        $this->voltar((int)$cliente['CLI_ID']);
    }

    public function sincronizar()
    {
        $this->validarCsrfPost();
        list($p,$cliente)=$this->contexto();
        $metaId=(int)($_POST['meta']??0);
        $this->canal($p,$cliente,$metaId);
        $ret=(new MetaService($metaId,(int)$cliente['CLI_ID']))->buscarTemplates();
        if(!isset($ret['data'])){Session::flash('error','Erro ao buscar templates do cliente.');$this->voltar((int)$cliente['CLI_ID']);}
        $ids=[];
        foreach($ret['data'] as $t){$ids[]=$t['id'];$this->templates->salvarOuAtualizar($metaId,$t);}
        $this->templates->inativarAusentes($metaId,$ids);
        Session::flash('success','Templates do cliente sincronizados.');
        $this->voltar((int)$cliente['CLI_ID']);
    }

    public function inativar()
    {
        $this->validarCsrfPost();
        list($p,$cliente)=$this->contexto();
        $id=(int)($_POST['id']??0);
        $t=$this->templates->buscarPorCliente($id,(int)$cliente['CLI_ID']);
        if(!$t){http_response_code(403);die('Template não autorizado para este Partner.');}
        $this->canal($p,$cliente,(int)$t['MTA_ID']);
        $this->templates->inativar($id,(int)$cliente['CLI_ID']);
        Session::flash('success','Template removido da listagem do cliente.');
        $this->voltar((int)$cliente['CLI_ID']);
    }
}
