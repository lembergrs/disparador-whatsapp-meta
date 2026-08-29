<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Campanha;
use Models\TemplateMeta;
use Models\Contato;
use Models\FilaEnvio;
use Models\CampanhaVariavel;
use Services\MetaService;
use Models\ListaContato;
use Models\ListaContatoItem;
use Models\MetaConta;
use Models\Cliente;
use Models\ConsumoMensal;
use Services\AnalyticsService;
use Services\MetaHealthService;

class CampanhaController extends Controller
{
    private $campanhaModel;

    private $templateModel;

    public function __construct()
    {
        Auth::check();

        $this->campanhaModel =
            new Campanha();

        $this->templateModel =
            new TemplateMeta();
    }

    public function index()
    {
        $usuario =
            Auth::usuario();

        $campanhas =
            $this->campanhaModel
            ->listarPorCliente(
                $usuario['cliente_id']
            );

        $templates =
            $this->templateModel
            ->listarAprovadosParaEnvioPorCliente(
                $usuario['cliente_id']
            );

        $listaModel =
            new ListaContato();

        $listas =
            $listaModel->listarPorCliente(
                $usuario['CLI_ID']
            );

        $contatoModel = new Contato();

        $camposContato =
            $contatoModel->camposJsonPorCliente(
                $usuario['cliente_id']
            );

        $clientePlano = (new Cliente())->buscarComPlano($usuario['CLI_ID']);
        $consumoMes = (new ConsumoMensal())->buscarMesAtual($usuario['CLI_ID']);
        $contasMeta = (new MetaConta())->listarPorCliente($usuario['CLI_ID']);
        $metaContaLimite = $contasMeta[0] ?? null;

        $this->view(
            'campanhas/index',
            [
                'titulo' => 'Campanhas',
                'campanhas' => $campanhas,
                'templates' => $templates,
                'camposContato' => $camposContato,
                'listas' => $listas,
                'clientePlano' => $clientePlano,
                'consumoMes' => $consumoMes,
                'contasMeta' => $contasMeta,
                'metaContaLimite' => $metaContaLimite
            ]
        );
    }

    public function criar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $listaModel = new ListaContato();
        $lista = $listaModel->buscar($_POST['lista'] ?? 0, $usuario['CLI_ID']);

        $template = $this->templateModel->buscarAprovadoParaEnvioPorCliente((int) ($_POST['template'] ?? 0), $usuario['CLI_ID']);

        if(!$lista || !$template){
            Session::flash('error', 'Lista ou template inválido.');
            $this->redirect('campanha');
        }


        $this->validarContaMetaParaEnvio((int) $template['MTA_ID'], (int) $usuario['CLI_ID']);

        $campanhaId =
            $this->campanhaModel->salvar([

                'cliente_id' => $usuario['CLI_ID'],
                'template_id' => $_POST['template'],
                'lista_id' => $_POST['lista'],
                'nome' => trim($_POST['nome']),
                'descricao' => trim($_POST['descricao']),
                'data_agendamento' =>
                    !empty($_POST['data_agendamento'])
                        ? $_POST['data_agendamento']
                        : date('Y-m-d H:i:s')

            ]);

        $variavelModel = new CampanhaVariavel();

        if(!empty($_POST['variaveis'])){

            foreach($_POST['variaveis'] as $variavel => $campo){

                $variavelModel->salvar(
                    $campanhaId,
                    $variavel,
                    $campo
                );

            }

        }

        $contatoModel = new Contato();

        $filaModel = new FilaEnvio();

        $listaItemModel =
            new ListaContatoItem();

        $contatos =
            $listaItemModel
            ->listarIdsDaLista(
                $_POST['lista']
            );

        foreach($contatos as $contato){

            $filaModel->adicionar(

                $campanhaId,
                $contato['CON_ID']

            );

        }

        $this->campanhaModel
            ->atualizarTotalContatos(

                $campanhaId,

                count($contatos)

            );

        if($this->campanhaModel->contarPorCliente($usuario['CLI_ID']) === 1){
            AnalyticsService::registrar('first_campaign_created', [
                'campaign_type'=>'scheduled', 'first_campaign'=>true
            ]);
        }

        \Core\Session::flash(

            'success',

            'Campanha criada com '
            . count($contatos)
            . ' contatos.'

        );

        $this->redirect(
            'campanha'
        );
    }
    
    public function detalhes()
    {
        $id = $_GET['id'] ?? null;

        if(!$id){
            \Core\Session::flash(
                'error',
                'Campanha não informada.'
            );

            $this->redirect('campanha');
        }

        $usuario = Auth::usuario();

        $campanha =
            $this->campanhaModel->buscarPorCliente($id, $usuario['CLI_ID']);

        if(!$campanha){
            \Core\Session::flash('error', 'Campanha não encontrada.');
            $this->redirect('campanha');
        }

        $fila =
            $this->campanhaModel->listarFilaPorCliente($id, $usuario['CLI_ID']);

        $this->view(
            'campanhas/detalhes',
            [
                'titulo' => 'Detalhes da Campanha',
                'campanha' => $campanha,
                'fila' => $fila
            ]
        );
    }

    public function cancelar()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $id = $_POST['id'] ?? null;

        if(!$id){

            \Core\Session::flash(
                'error',
                'Campanha não informada.'
            );

            $this->redirect('campanha');
        }

        $this->campanhaModel->cancelar(
            $id,
            $usuario['CLI_ID']
        );

        \Core\Session::flash(
            'success',
            'Campanha cancelada com sucesso.'
        );

        $this->redirect('campanha/detalhes&id=' . $id);
    }

    public function preview()
    {
        $usuario = Auth::usuario();

        $id = $_GET['id'];

        $campanha =
            $this->campanhaModel
            ->buscarPorCliente($id, $usuario['CLI_ID']);

        if(!$campanha){
            \Core\Session::flash('error', 'Campanha não encontrada.');
            $this->redirect('campanha');
        }





        $contato =
            $this->campanhaModel
            ->buscarContatoExemploPorCliente($id, $usuario['CLI_ID']);





        $variavelModel =
            new CampanhaVariavel();





        $variaveis =
            $variavelModel
            ->listarPorCampanha($id);





        $dadosContato =
            json_decode(
                $contato['CON_DadosJson'],
                true
            );





        $componentes =
            json_decode(
                $campanha['TMP_Componentes'],
                true
            );





        $body = '';

        foreach($componentes as $comp){

            if(
                strtoupper($comp['type'])
                == 'BODY'
            ){

                $body =
                    $comp['text'];

            }

        }






        foreach($variaveis as $var){

            $valor =
                $dadosContato[
                    $var['CPV_Campo']
                ] ?? '';

            $body =
                str_replace(

                    '{{'
                    . $var['CPV_Variavel']
                    . '}}',

                    $valor,

                    $body

                );

        }






        $this->view(

            'campanhas/preview',

            [

                'titulo' =>
                    'Pré-visualização',

                'campanha' =>
                    $campanha,

                'contato' =>
                    $contato,

                'variaveis' =>
                    $variaveis,

                'mensagem' =>
                    $body

            ]

        );
    }

    public function reagendar()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $id =
            $_POST['id'] ?? null;

        $dataAgendamento =
            $_POST['data_agendamento'] ?? null;

        if(!$id || !$dataAgendamento){

            \Core\Session::flash(
                'error',
                'Informe a campanha e a nova data de envio.'
            );

            $this->redirect('campanha');

            return;
        }

        $campanha = $this->campanhaModel->buscarPorCliente($id, $usuario['CLI_ID']);
        if(!$campanha){
            Session::flash('error', 'Campanha não encontrada.');
            $this->redirect('campanha');
        }

        $this->validarContaMetaParaEnvio((int) $campanha['MTA_ID'], (int) $usuario['CLI_ID']);

        $this->campanhaModel->reagendar(
            $id,
            $usuario['CLI_ID'],
            $dataAgendamento
        );

        $this->campanhaModel->resetarFilaPorCliente(
            $id,
            $usuario['CLI_ID']
        );

        \Core\Session::flash(
            'success',
            'Campanha reagendada com sucesso.'
        );

        $this->redirect(
            'campanha/detalhes&id=' . $id
        );
    }

    public function enviarTeste()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $campanhaId =
            $_POST['campanha_id'];

        $telefone =
            $_POST['telefone'];

        $campanha =
            $this->campanhaModel->buscarPorCliente($campanhaId, $usuario['CLI_ID']);

        if(!$campanha){
            \Core\Session::flash('error', 'Campanha não encontrada.');
            $this->redirect('campanha');
        }


        $this->validarContaMetaParaEnvio((int) $campanha['MTA_ID'], (int) $usuario['CLI_ID']);

        $contato =
            $this->campanhaModel->buscarContatoExemploPorCliente($campanhaId, $usuario['CLI_ID']);

        $variavelModel =
            new \Models\CampanhaVariavel();

        $variaveis =
            $variavelModel->listarPorCampanha($campanhaId);

        $dadosContato =
            json_decode(
                $contato['CON_DadosJson'],
                true
            );

        if(!is_array($dadosContato)){
            $dadosContato = [];
        }

        $parametros = [];

        foreach($variaveis as $var){

            $campo =
                $var['CPV_Campo'];

            $parametros[$var['CPV_Variavel']] =
                $dadosContato[$campo] ?? '';

        }

        try{
            $telefone =
                preg_replace(
                    '/\D/',
                    '',
                    $_POST['telefone']
                );
                
            $telefone = '55' . $telefone;

            $meta =
                new MetaService(
                    $campanha['MTA_ID'],
                    $usuario['CLI_ID']
                );

            $retorno =
                $meta->enviarTemplate(
                    $telefone,
                    $campanha,
                    $parametros
                );

            if(isset($retorno['messages'][0]['id'])){

                \Core\Session::flash(
                    'success',
                    'Mensagem de teste enviada com sucesso.'
                );

            }else{

                \Core\Session::flash(
                    'error',
                    $retorno['error']['message']
                    ?? 'Erro ao enviar teste.'
                );

            }

        }catch(\Exception $e){

            \Core\Session::flash(
                'error',
                $e->getMessage()
            );

        }

        $this->redirect(
            'campanha/preview&id=' . $campanhaId
        );
    }

    private function validarContaMetaParaEnvio(int $metaId, int $clienteId): void
    {
        $conta = (new MetaConta())->buscarPorCliente($metaId, $clienteId);
        if(!$conta){
            Session::flash('error', 'Conta Meta não encontrada para este cliente.');
            $this->redirect('campanha');
        }

        $aptidao = MetaHealthService::avaliarAptidaoEnvio($conta);
        if(!$aptidao['permitido']){
            Session::flash('error', $aptidao['mensagem']);
            $this->redirect('campanha');
        }
    }

}
