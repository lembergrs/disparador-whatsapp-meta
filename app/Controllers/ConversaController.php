<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Core\Csrf;
use Models\Conversa;
use Models\Usuario;
use Services\MetaService;

class ConversaController extends Controller
{
    private $conversaModel;

    public function __construct()
    {
        Auth::cliente();

        $this->conversaModel = new Conversa();
    }

    public function index()
    {
        $usuario = Auth::usuario();

        $busca =
            trim($_GET['busca'] ?? '');

        $status =
            $_GET['status'] ?? '';

        $etiqueta =
            $_GET['etiqueta'] ?? '';

        $responsavel =
            $_GET['responsavel'] ?? '';

        $conversas =
            $this->conversaModel->listarConversas(
                $usuario['CLI_ID'],
                $busca,
                $status,
                $etiqueta,
                $usuario,
                $this->responsavelPermitido($usuario, $responsavel)
            );

        $etiquetas =
            $this->conversaModel->listarEtiquetas(
                $usuario['CLI_ID']
            );

        $atendentes = [];

        if($this->podeGerenciarConversas($usuario)){
            $usuarioModel = new Usuario();
            $atendentes = $usuarioModel
                ->listarAtivosAtendimentoPorCliente(
                    $usuario['CLI_ID']
                );
        }

        $podeAtribuirConversa =
            $this->podeGerenciarConversas($usuario);

        $conversaSelecionada = null;
        $mensagens = [];
        $janelaAberta = false;

        if(!empty($_GET['id'])){

            $conversaSelecionada =
                $this->conversaModel->buscarAcessivel(
                    $_GET['id'],
                    $usuario['CLI_ID'],
                    $usuario
                );

            if($conversaSelecionada){

                $mensagens =
                    $this->conversaModel
                    ->listarMensagens(
                        $conversaSelecionada['CVS_ID']
                    );

                $janelaAberta =
                    $this->janelaAtendimentoAberta(
                        $conversaSelecionada['CVS_ID']
                    );

                $this->conversaModel
                    ->marcarComoLida(
                        $conversaSelecionada['CVS_ID'],
                        $usuario['CLI_ID']
                    );
            }
        }

        $this->view(
            'conversas/index',
            [
                'titulo' => 'Conversas',
                'conversas' => $conversas,
                'etiquetas' => $etiquetas,
                'busca' => $busca,
                'status' => $status,
                'etiqueta' => $etiqueta,
                'responsavel' => $responsavel,
                'atendentes' => $atendentes,
                'podeAtribuirConversa' => $this->podeGerenciarConversas($usuario),
                'conversaSelecionada' => $conversaSelecionada,
                'mensagens' => $mensagens,
                'janelaAberta' => $janelaAberta
            ]
        );
    }

    public function enviar()
    {
        $usuario = Auth::usuario();

        $conversaId =
            $_POST['conversa_id'] ?? null;

        $mensagem =
            trim($_POST['mensagem'] ?? '');

        if(!Csrf::validar($_POST['csrf_token'] ?? '')){
            Session::flash(
                'error',
                'Token de segurança inválido.'
            );

            $this->redirect('conversa');

            return;
        }

        if(!$conversaId || $mensagem == ''){

            Session::flash(
                'error',
                'Informe a mensagem.'
            );

            $this->redirect('conversa');

            return;
        }

        $conversa =
            $this->conversaModel->buscarAcessivel(
                $conversaId,
                $usuario['CLI_ID'],
                $usuario
            );

        if(!$conversa){

            Session::flash(
                'error',
                'Conversa não encontrada.'
            );

            $this->redirect('conversa');

            return;
        }

        if(!$this->janelaAtendimentoAberta($conversaId)){

            Session::flash(
                'error',
                'A janela de atendimento de 24 horas está fechada. Use um template aprovado para iniciar nova conversa.'
            );

            $this->redirect(
                'conversa&id=' . $conversaId
            );

            return;
        }

        try{

            $meta =
                new MetaService(
                    $conversa['MTA_ID']
                );

            $response =
                $meta->enviarTexto(
                    $conversa['CVS_Numero'],
                    $mensagem
                );

            $messageId = null;
            $status = 'erro';

            if(isset($response['response']['messages'][0]['id'])){

                $messageId =
                    $response['response']['messages'][0]['id'];

                $status = 'enviado';

            }

            $this->conversaModel->salvarMensagem([

                'conversa_id' =>
                    $conversaId,

                'direcao' =>
                    'enviada',

                'tipo' =>
                    'text',

                'texto' =>
                    $mensagem,

                'message_id' =>
                    $messageId,

                'status' =>
                    $status,

                'retorno' =>
                    $response,

                'data_mensagem' =>
                    date('Y-m-d H:i:s')

            ]);

            if($status == 'enviado'){

                Session::flash(
                    'success',
                    'Mensagem enviada com sucesso.'
                );

            }else{

                $erro =
                    $response['response']['error']['message']
                    ??
                    'Erro ao enviar mensagem.';

                Session::flash(
                    'error',
                    $erro
                );
            }

        }catch(\Exception $e){

            Session::flash(
                'error',
                $e->getMessage()
            );

        }

        $this->redirect(
            'conversa&id=' . $conversaId
        );
    }

    private function janelaAtendimentoAberta($conversaId)
    {
        $ultimaRecebida =
            $this->conversaModel
            ->ultimaMensagemRecebida(
                $conversaId
            );

        if(!$ultimaRecebida){
            return false;
        }

        $limite =
            strtotime(
                $ultimaRecebida['MSG_DataMensagem']
            ) + (24 * 60 * 60);

        return time() <= $limite;
    }

    private function podeGerenciarConversas($usuario)
    {
        return in_array(
            $usuario['nivel'] ?? null,
            ['cliente_admin', 'cliente'],
            true
        );
    }

    private function validarCsrfAjax()
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if(!Csrf::validar($token)){
            http_response_code(403);

            echo json_encode([
                'sucesso' => false,
                'erro' => 'Token de segurança inválido.'
            ]);

            return false;
        }

        return true;
    }

    private function acessoPerdidoAjax($json = false)
    {
        http_response_code(403);

        if($json){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'sucesso' => false,
                'acesso_perdido' => true,
                'erro' => 'Esta conversa foi transferida ou não está mais atribuída a você.'
            ]);
            return;
        }

        echo '<div class="card-body text-center text-muted d-flex align-items-center justify-content-center">Esta conversa foi transferida ou não está mais atribuída a você.</div>';
    }

    public function verificarAtualizacao()
    {
        header('Content-Type: application/json; charset=utf-8');

        $usuario =
            Auth::usuario();

        $ultimaLocal =
            $_GET['ultima']
            ?? '';

        $dados =
            $this->conversaModel
            ->ultimaAtualizacaoCliente(
                $usuario['CLI_ID']
            );

        $ultimaBanco =
            $dados['ultima']
            ?? '';

        echo json_encode([
            'atualizar' =>
                $ultimaBanco != $ultimaLocal,

            'ultima' =>
                $ultimaBanco
        ]);
    }

    public function ajaxLista()
    {
        $usuario =
            Auth::usuario();

        $busca =
            trim($_GET['busca'] ?? '');

        $status =
            $_GET['status'] ?? '';

        $etiqueta =
            $_GET['etiqueta'] ?? '';

        $responsavel =
            $_GET['responsavel'] ?? '';

        $conversas =
            $this->conversaModel->listarConversas(
                $usuario['CLI_ID'],
                $busca,
                $status,
                $etiqueta,
                $usuario,
                $this->responsavelPermitido($usuario, $_GET['responsavel'] ?? ''),
                $_GET['manter_aberta'] ?? null
            );

        $podeAtribuirConversa =
            $this->podeGerenciarConversas($usuario);

        $conversaSelecionada = null;

        if(!empty($_GET['id'])){

            $conversaSelecionada =
                $this->conversaModel
                ->buscarAcessivel(
                    $_GET['id'],
                    $usuario['CLI_ID'],
                    $usuario
                );
        }

        require __DIR__ . '/../Views/conversas/partials/lista.php';
    }

    public function ajaxMensagens()
    {
        $usuario = Auth::usuario();

        $id =
            $_GET['id'] ?? null;

        $marcarLida =
            $_GET['marcar_lida'] ?? 'N';

        if(!$id){
            exit;
        }

        $conversa =
            $this->conversaModel->buscarAcessivel(
                $id,
                $usuario['CLI_ID'],
                $usuario
            );

        if(!$conversa){
            $this->acessoPerdidoAjax(false);
            return;
        }

        $mensagens =
            $this->conversaModel->listarMensagens(
                $id
            );

        if($marcarLida == 'S'){

            if(!Csrf::validar($_GET['csrf_token'] ?? '')){
                $this->acessoPerdidoAjax(false);
                return;
            }

            $this->conversaModel->marcarComoLida(
                $id,
                $usuario['CLI_ID']
            );

        }

        require __DIR__ . '/../Views/conversas/partials/mensagens.php';
    }

    public function enviarAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        if(!$this->validarCsrfAjax()){
            return;
        }

        try{

            $usuario = Auth::usuario();

            $conversaId =
                $_POST['conversa_id'] ?? null;

            $mensagem =
                trim($_POST['mensagem'] ?? '');

            if(!$conversaId || $mensagem == ''){

                echo json_encode([
                    'sucesso' => false,
                    'erro' => 'Informe a mensagem.'
                ]);

                return;
            }

            $conversa =
                $this->conversaModel->buscarAcessivel(
                    $conversaId,
                    $usuario['CLI_ID'],
                    $usuario
                );

            if(!$conversa){
                $this->acessoPerdidoAjax(true);

                return;
            }

            if(!$this->janelaAtendimentoAberta($conversaId)){

                echo json_encode([
                    'sucesso' => false,
                    'erro' => 'A janela de atendimento de 24 horas está fechada. Use um template aprovado para iniciar nova conversa.'
                ]);

                return;
            }

            $meta =
                new MetaService(
                    $conversa['MTA_ID']
                );

            $response =
                $meta->enviarTexto(
                    $conversa['CVS_Numero'],
                    $mensagem
                );

            $messageId = null;
            $status = 'erro';

            if(isset($response['response']['messages'][0]['id'])){

                $messageId =
                    $response['response']['messages'][0]['id'];

                $status = 'enviado';

            }

            $this->conversaModel->salvarMensagem([

                'conversa_id' =>
                    $conversaId,

                'direcao' =>
                    'enviada',

                'tipo' =>
                    'text',

                'texto' =>
                    $mensagem,

                'message_id' =>
                    $messageId,

                'status' =>
                    $status,

                'retorno' =>
                    $response,

                'data_mensagem' =>
                    date('Y-m-d H:i:s')

            ]);

            if($status == 'enviado'){

                echo json_encode([
                    'sucesso' => true,
                    'message_id' => $messageId
                ]);

                return;
            }

            echo json_encode([
                'sucesso' => false,
                'erro' =>
                    $response['response']['error']['message']
                    ?? 'Erro ao enviar mensagem.'
            ]);

        }catch(\Exception $e){

            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ]);

        }
    }

    public function marcarNaoLidaAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        if(!$this->validarCsrfAjax()){
            return;
        }

        $usuario = Auth::usuario();

        $id =
            $_POST['conversa_id'] ?? null;

        if(!$id){

            echo json_encode([
                'sucesso' => false,
                'erro' => 'Conversa não informada.'
            ]);

            return;
        }

        $conversa = $this->conversaModel->buscarAcessivel(
            $id,
            $usuario['CLI_ID'],
            $usuario
        );

        if(!$conversa){
            $this->acessoPerdidoAjax(true);

            return;
        }

        $ok =
            $this->conversaModel->marcarComoNaoLida(
                $id,
                $usuario['CLI_ID']
            );

        echo json_encode([
            'sucesso' => $ok
        ]);
    }

    public function etiquetasAjax()
    {
        $usuario = Auth::usuario();

        $id =
            $_GET['conversa_id'] ?? null;

        if(!$id){
            exit;
        }

        $conversa =
            $this->conversaModel->buscarAcessivel(
                $id,
                $usuario['CLI_ID'],
                $usuario
            );

        if(!$conversa){
            exit;
        }

        $etiquetas =
            $this->conversaModel->listarEtiquetas(
                $usuario['CLI_ID']
            );

        $etiquetasConversa =
            $this->conversaModel->etiquetasDaConversa(
                $id,
                $usuario['CLI_ID']
            );

        require __DIR__ . '/../Views/conversas/partials/etiquetas.php';
    }

    public function salvarEtiquetasAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        if(!$this->validarCsrfAjax()){
            return;
        }

        $usuario = Auth::usuario();

        $id =
            $_POST['conversa_id'] ?? null;

        $etiquetas =
            $_POST['etiquetas'] ?? [];

        if(!$id){

            echo json_encode([
                'sucesso' => false,
                'erro' => 'Conversa não informada.'
            ]);

            return;
        }

        if(!is_array($etiquetas)){
            $etiquetas = [];
        }

        $conversa = $this->conversaModel->buscarAcessivel(
            $id,
            $usuario['CLI_ID'],
            $usuario
        );

        if(!$conversa){
            $this->acessoPerdidoAjax(true);

            return;
        }

        $ok =
            $this->conversaModel->salvarEtiquetasConversa(
                $id,
                $usuario['CLI_ID'],
                $etiquetas
            );

        echo json_encode([
            'sucesso' => $ok
        ]);
    }

    public function criarEtiquetaAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        if(!$this->validarCsrfAjax()){
            return;
        }

        $usuario = Auth::usuario();

        $nome =
            trim($_POST['nome'] ?? '');

        $cor =
            $_POST['cor'] ?? 'secondary';

        $coresPermitidas = [
            'secondary',
            'primary',
            'success',
            'danger',
            'warning',
            'info',
            'dark'
        ];

        if(!in_array($cor, $coresPermitidas)){
            $cor = 'secondary';
        }

        if($nome == ''){

            echo json_encode([
                'sucesso' => false,
                'erro' => 'Informe o nome da etiqueta.'
            ]);

            return;
        }

        $id =
            $this->conversaModel->criarEtiqueta(
                $usuario['CLI_ID'],
                $nome,
                $cor
            );

        echo json_encode([
            'sucesso' => true,
            'id' => $id
        ]);
    }

    public function ajaxConversa()
    {
        $usuario = Auth::usuario();

        $podeAtribuirConversa =
            $this->podeGerenciarConversas($usuario);

        $conversaSelecionada = null;
        $mensagens = [];
        $janelaAberta = false;

        $id =
            $_GET['id'] ?? null;

        if($id){

            $conversaSelecionada =
                $this->conversaModel->buscarAcessivel(
                    $id,
                    $usuario['CLI_ID'],
                    $usuario
                );

            if($conversaSelecionada){

                $mensagens =
                    $this->conversaModel->listarMensagens(
                        $id
                    );

                $janelaAberta =
                    $this->janelaAtendimentoAberta(
                        $id
                    );

                if(Csrf::validar($_GET['csrf_token'] ?? '')){
                    $this->conversaModel->marcarComoLida(
                        $id,
                        $usuario['CLI_ID']
                    );
                }

            }else{
                $this->acessoPerdidoAjax(false);
                return;
            }

        }

        require __DIR__ . '/../Views/conversas/partials/painel.php';
        
    }


    public function atribuirResponsavelAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        if(!$this->validarCsrfAjax()){
            return;
        }

        $usuario = Auth::usuario();

        if(!$this->podeGerenciarConversas($usuario)){
            http_response_code(403);

            echo json_encode([
                'sucesso' => false,
                'erro' => 'Permissão negada.'
            ]);

            return;
        }

        $conversaId = (int) ($_POST['conversa_id'] ?? 0);

        $responsavelIdPost = trim((string) ($_POST['responsavel_id'] ?? ''));
        $responsavelId = $responsavelIdPost === ''
            ? null
            : (int) $responsavelIdPost;

        if($responsavelId === 0){
            $responsavelId = null;
        }

        if(!$conversaId){
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Informe a conversa.'

            ]);

            return;
        }

        $resultado = $this->conversaModel->atribuirResponsavel(
            $conversaId,
            $usuario['CLI_ID'],
            $responsavelId
        );

        echo json_encode($resultado);
    }

    private function responsavelPermitido($usuario, $responsavel)
    {
        if(!$this->podeGerenciarConversas($usuario)){
            return '';
        }

        return $responsavel;
    }

}
