<?php
namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\ParceiroApi;
use Models\ParceiroFinanceiro;
use Models\ParceiroApiAuditoria;
use Services\ParceiroFinanceiroService;

class ParceiroAdminController extends Controller
{
    private $model;
    private $financeiro;

    public function __construct()
    {
        Auth::admin();
        $this->model=new ParceiroApi();
        $this->financeiro=new ParceiroFinanceiro();
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

        $clientes=$this->model->listarClientesAutorizaveisAdmin();

        $contas=[];
        foreach($clientes as $cliente){
            $contas[(int)$cliente['CLI_ID']]=$this->model->listarContasClienteAdmin($cliente['CLI_ID']);
        }

        $novaApiKey=Session::get('partner_api_key_once');
        if($novaApiKey !== null){
            Session::remove('partner_api_key_once');
        }

        $this->view('parceiros/detalhe',[
            'titulo'=>'Parceiro API',
            'parceiro'=>$parceiro,
            'canais'=>$this->model->listarCanaisAutorizados($id),
            'chaves'=>$this->model->listarChavesAdmin($id),
            'clientes'=>$clientes,
            'contasPorCliente'=>$contas,
            'novaApiKey'=>$novaApiKey,
            'planosPartner'=>$this->financeiro->listarPlanosAdmin(),
            'assinaturaPartner'=>$this->financeiro->assinaturaAtiva($id),
            'cobrancasPartner'=>$this->financeiro->listarCobrancasAdmin($id),
            'clientesFaturaveis'=>$this->financeiro->contarClientesFaturaveis($id)
        ]);
    }

    public function auditoria()
    {
        $filtros=[
            'parceiro_id'=>(int)($_GET['parceiro_id']??0),
            'evento'=>trim((string)($_GET['evento']??'')),
            'severidade'=>trim((string)($_GET['severidade']??'')),
            'data_inicio'=>trim((string)($_GET['data_inicio']??'')),
            'data_fim'=>trim((string)($_GET['data_fim']??''))
        ];
        $this->view('parceiros/auditoria',[
            'titulo'=>'Auditoria Partner API',
            'registros'=>(new ParceiroApiAuditoria())->listarAdmin($filtros,200),
            'parceiros'=>$this->model->listarAdmin(),
            'filtros'=>$filtros
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

    public function salvarPlanoFinanceiro()
    {
        $this->validarCsrfPost();
        try{
            $nome=trim($_POST['nome'] ?? '');
            if($nome===''){ throw new \DomainException('Informe o nome da faixa.'); }
            $valor=str_replace(',','.',trim((string)($_POST['valor'] ?? '0')));
            $this->financeiro->salvarPlanoAdmin($nome,(int)($_POST['min_clientes'] ?? 1),$_POST['max_clientes'] ?? null,(float)$valor);
            Session::flash('success','Faixa Partner cadastrada.');
        }catch(\Throwable $e){ Session::flash('error',$e->getMessage()); }
        $this->redirect('parceiroAdmin/detalhe&id='.(int)($_POST['parceiro_id'] ?? 0));
    }

    public function inativarPlanoFinanceiro()
    {
        $this->validarCsrfPost();
        $this->financeiro->inativarPlanoAdmin((int)($_POST['plano_id'] ?? 0));
        Session::flash('success','Faixa Partner inativada.');
        $this->redirect('parceiroAdmin/detalhe&id='.(int)($_POST['parceiro_id'] ?? 0));
    }

    public function salvarAssinaturaFinanceira()
    {
        $this->validarCsrfPost();
        $parceiroId=(int)($_POST['parceiro_id'] ?? 0);
        $parceiro=$this->model->buscarAdmin($parceiroId);
        if(!$parceiro || ($parceiro['PAR_StatusCadastro'] ?? '')!=='aprovado'){
            Session::flash('error','Aprove o cadastro Partner antes de configurar a assinatura.');
            $this->redirect('parceiroAdmin/detalhe&id='.$parceiroId);
        }
        try{
            $valor=(float)str_replace(',','.',trim((string)($_POST['valor_implantacao'] ?? '0')));
            if($valor < 0){ throw new \DomainException('Valor de implantação inválido.'); }
            $this->financeiro->criarOuAtualizarAssinaturaAdmin($parceiroId,$valor,(int)($_POST['dia_vencimento'] ?? 10));
            Session::flash('success','Configuração financeira Partner salva.');
        }catch(\Throwable $e){ Session::flash('error',$e->getMessage()); }
        $this->redirect('parceiroAdmin/detalhe&id='.$parceiroId);
    }

    public function cobrarImplantacao()
    {
        $this->validarCsrfPost();
        $parceiroId=(int)($_POST['parceiro_id'] ?? 0);
        try{
            $assinatura=$this->financeiro->assinaturaAtiva($parceiroId);
            if(!$assinatura){ throw new \DomainException('Configure a assinatura Partner antes de gerar a cobrança.'); }
            $vencimento=trim((string)($_POST['vencimento'] ?? ''));
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$vencimento)){ throw new \DomainException('Informe um vencimento válido.'); }
            $resultado=(new ParceiroFinanceiroService())->criarCobrancaImplantacao($parceiroId,(float)$assinatura['PAS_ValorImplantacao'],$vencimento);
            $msg=!empty($resultado['integracao']['sucesso']) ? 'Cobrança de implantação disponível no Asaas.' : ($resultado['integracao']['mensagem'] ?? 'Cobrança criada, mas a integração com o Asaas precisa ser revisada.');
            Session::flash(!empty($resultado['integracao']['sucesso'])?'success':'error',$msg);
        }catch(\Throwable $e){ Session::flash('error',$e->getMessage()); }
        $this->redirect('parceiroAdmin/detalhe&id='.$parceiroId);
    }

    public function cobrarMensalidade()
    {
        $this->validarCsrfPost();
        $parceiroId=(int)($_POST['parceiro_id'] ?? 0);
        try{
            $competencia=preg_replace('/\D/','',(string)($_POST['competencia'] ?? ''));
            if(!preg_match('/^\d{6}$/',$competencia)){ throw new \DomainException('Competência inválida.'); }
            $vencimento=trim((string)($_POST['vencimento'] ?? ''));
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$vencimento)){ throw new \DomainException('Informe um vencimento válido.'); }
            $resultado=(new ParceiroFinanceiroService())->criarCobrancaMensal($parceiroId,$competencia,$vencimento);
            $msg=!empty($resultado['integracao']['sucesso']) ? 'Mensalidade Partner gerada no Asaas.' : ($resultado['integracao']['mensagem'] ?? 'Mensalidade criada, mas a integração com o Asaas precisa ser revisada.');
            Session::flash(!empty($resultado['integracao']['sucesso'])?'success':'error',$msg);
        }catch(\Throwable $e){ Session::flash('error',$e->getMessage()); }
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
        // A API key precisa de armazenamento próprio: Session::flash() usa uma única
        // chave global ('flash'), então a mensagem de sucesso sobrescrevia o segredo.
        Session::set('partner_api_key_once',$segredo);
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
