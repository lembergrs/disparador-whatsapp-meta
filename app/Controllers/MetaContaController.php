<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\MetaConta;
use Models\Cliente;
use Models\ConfiguracaoSite;
use Services\MetaService;

class MetaContaController extends Controller
{
    private $metaModel;

    private $clienteModel;

    private $configuracaoSiteModel;





    public function __construct()
    {
        Auth::admin();

        $this->metaModel =
            new MetaConta();

        $this->clienteModel =
            new Cliente();

        $this->configuracaoSiteModel =
            new ConfiguracaoSite();
    }





    private function gerarWebhookVerifyToken()
    {
        return bin2hex(
            random_bytes(32)
        );
    }


    private function prepararDadosAutoResposta($dados)
    {
        $dados['auto_resposta_ativa'] =
            ($dados['auto_resposta_ativa'] ?? 'N') == 'S'
            ? 'S'
            : 'N';

        $dados['auto_resposta_texto'] =
            trim(
                $dados['auto_resposta_texto'] ?? ''
            );

        $dados['auto_resposta_intervalo_minutos'] =
            max(
                1,
                (int) ($dados['auto_resposta_intervalo_minutos'] ?? 1440)
            );

        if($dados['auto_resposta_ativa'] == 'S' && $dados['auto_resposta_texto'] == ''){

            Session::flash(
                'error',
                'Informe o texto da auto resposta para ativá-la.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        return $dados;
    }

    private function prepararDadosWebhookVerifyToken($dados)
    {
        $dados['webhook_verify_token'] =
            trim(
                $dados['webhook_verify_token'] ?? ''
            );

        if($dados['webhook_verify_token'] === ''){

            $dados['webhook_verify_token'] =
                $this->gerarWebhookVerifyToken();
        }

        return $dados;
    }

    public function index()
    {
        $contas =
            $this->metaModel->listar();

        $clientes =
            $this->clienteModel->listar();

        $colunaWebhookVerifyTokenExiste =
            $this->metaModel->colunaWebhookVerifyTokenExiste();

        try{
            $configuracaoWhatsappSite = $this->configuracaoSiteModel->buscar();
            $formWhatsappSite = Session::get('whatsapp_site_form');
            Session::remove('whatsapp_site_form');
            if(is_array($formWhatsappSite)){
                $configuracaoWhatsappSite = array_merge($configuracaoWhatsappSite, $formWhatsappSite);
            }
            $contasWhatsappSite = $this->configuracaoSiteModel->contasElegiveis();
            $configuracaoWhatsappSiteDisponivel = true;
        }catch(\Throwable $e){
            $configuracaoWhatsappSite = null;
            $contasWhatsappSite = [];
            $configuracaoWhatsappSiteDisponivel = false;
        }





        $this->view(
            'meta_contas/index',
            [

                'titulo' => 'Contas Meta',

                'contas' => $contas,

                'clientes' => $clientes,

                'colunaWebhookVerifyTokenExiste' => $colunaWebhookVerifyTokenExiste,

                'colunasAutoRespostaExistem' => $this->metaModel->colunasAutoRespostaExistem(),
                'configuracaoWhatsappSite' => $configuracaoWhatsappSite,
                'contasWhatsappSite' => $contasWhatsappSite,
                'configuracaoWhatsappSiteDisponivel' => $configuracaoWhatsappSiteDisponivel

            ]
        );
    }

    public function salvarWhatsappSite()
    {
        $this->validarCsrfPost();

        $ativo = ($_POST['whatsapp_site_ativo'] ?? 'N') === 'S' ? 'S' : 'N';
        $metaContaId = (int) ($_POST['meta_conta_id'] ?? 0);
        $mensagem = trim(strip_tags((string) ($_POST['mensagem_inicial'] ?? '')));

        if(mb_strlen($mensagem) > 500){
            $this->preservarFormularioWhatsappSite($ativo, $metaContaId, $mensagem);
            Session::flash('error', 'A mensagem inicial deve possuir no máximo 500 caracteres.');
            $this->redirect('metaConta');
        }

        if($ativo === 'S'){
            $conta = $this->configuracaoSiteModel->contaElegivel($metaContaId);
            if(!$conta || !ConfiguracaoSite::normalizarTelefone($conta['MTA_NumeroTelefone'] ?? '')){
                $this->preservarFormularioWhatsappSite($ativo, $metaContaId, $mensagem);
                Session::flash('error', 'Selecione um número Meta ativo, conectado e com telefone internacional válido.');
                $this->redirect('metaConta');
            }
            if($mensagem === ''){
                $this->preservarFormularioWhatsappSite($ativo, $metaContaId, $mensagem);
                Session::flash('error', 'Informe a mensagem inicial do botão de WhatsApp.');
                $this->redirect('metaConta');
            }
        }

        $this->configuracaoSiteModel->salvar($ativo, $metaContaId, $mensagem);
        Session::flash('success', 'Atendimento pelo WhatsApp no site atualizado com sucesso.');
        $this->redirect('metaConta');
    }

    private function preservarFormularioWhatsappSite($ativo, $metaContaId, $mensagem)
    {
        Session::set('whatsapp_site_form', [
            'CWS_Ativo' => $ativo,
            'MTA_ID' => $metaContaId,
            'CWS_Mensagem' => $mensagem
        ]);
    }





    public function salvar()
    {
        $this->validarCsrfPost();

        $clienteId =
            (int) ($_POST['cliente'] ?? 0);

        $limite =
            $this->metaModel
            ->avaliarLimiteNumerosPorCliente(
                $clienteId
            );

        if(!$limite['permitido']){

            Session::flash(
                'error',
                $limite['mensagem']
            );

            $this->redirect(
                'metaConta'
            );
        }

        if(trim((string) ($_POST['token'] ?? '')) === ''){
            Session::flash('error', 'Informe o token da Meta.');
            $this->redirect('metaConta');
        }

        $dados =
            $this->prepararDadosAutoResposta(
                $this->prepararDadosWebhookVerifyToken(
                    $_POST
                )
            );

        if(!$this->metaModel->colunaWebhookVerifyTokenExiste()){

            Session::flash(
                'error',
                'A coluna MTA_WebhookVerifyToken não existe em meta_contas. Crie a coluna antes de salvar o token do webhook.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        if(!$this->metaModel->colunasAutoRespostaExistem()){

            Session::flash(
                'error',
                'As colunas de auto resposta não existem em meta_contas. Crie as colunas antes de salvar a configuração.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        $this->metaModel->salvar(
            $dados
        );





        Session::flash(
            'success',
            'Conta Meta cadastrada com sucesso.'
        );





        $this->redirect(
            'metaConta'
        );
    }





    public function inativar()
    {
        $this->validarCsrfPost();

        $id = $_GET['id'];

        $this->metaModel->inativar(
            $id
        );





        Session::flash(
            'success',
            'Conta Meta inativada.'
        );





        $this->redirect(
            'metaConta'
        );
    }

    public function testar()
    {
        $this->validarCsrfPost();

        $id = $_GET['id'];

        try{

            $meta =
                new MetaService($id);

            $resultado =
                $meta->testarConexao();





            if($resultado['sucesso']){

                Session::flash(
                    'success',
                    'Conexão realizada com sucesso.'
                );

            }else{

                Session::flash(
                    'error',
                    'Erro ao conectar com a Meta.'
                );

            }

        }catch(\Exception $e){

            Session::flash(
                'error',
                $e->getMessage()
            );

        }





        $this->redirect(
            'metaConta'
        );
    }

    public function atualizar()
    {
        $this->validarCsrfPost();

        $id =
            (int) ($_POST['id'] ?? 0);

        $contaAtual =
            $this->metaModel->buscar(
                $id
            );

        if(!$contaAtual){

            Session::flash(
                'error',
                'Conta Meta inválida.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        $clienteId =
            (int) ($_POST['cliente'] ?? 0);

        $clienteAlterado =
            (int) $contaAtual['CLI_ID']
            !==
            $clienteId;

        if($clienteAlterado){

            $limite =
                $this->metaModel
                ->avaliarLimiteNumerosPorCliente(
                    $clienteId,
                    $id
                );

            if(!$limite['permitido']){

                Session::flash(
                    'error',
                    $limite['mensagem']
                );

                $this->redirect(
                    'metaConta'
                );
            }
        }

        $dados =
            $this->prepararDadosAutoResposta(
                $this->prepararDadosWebhookVerifyToken(
                    $_POST
                )
            );

        if(!$this->metaModel->colunaWebhookVerifyTokenExiste()){

            Session::flash(
                'error',
                'A coluna MTA_WebhookVerifyToken não existe em meta_contas. Crie a coluna antes de salvar o token do webhook.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        if(!$this->metaModel->colunasAutoRespostaExistem()){

            Session::flash(
                'error',
                'As colunas de auto resposta não existem em meta_contas. Crie as colunas antes de salvar a configuração.'
            );

            $this->redirect(
                'metaConta'
            );
        }

        $this->metaModel->atualizar(
            $id,
            $dados
        );





        Session::flash(
            'success',
            'Conta Meta atualizada com sucesso.'
        );





        $this->redirect(
            'metaConta'
        );
    }

}
