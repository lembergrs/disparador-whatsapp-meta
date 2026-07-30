<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Core\Csrf;
use Models\Conversa;
use Models\Contato;
use Models\Usuario;
use Models\MetaConta;
use Models\TemplateMeta;
use Services\ConversaTemplateService;
use Services\MetaService;
use Services\MensagemStatusService;

class ConversaController extends Controller
{
    private $conversaModel;

    public function __construct()
    {
        Auth::check();

        $usuario = Auth::usuario();

        if(!$this->usuarioPodeAcessarConversas($usuario)){
            die('Acesso negado');
        }

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
                'janelaAberta' => $janelaAberta,
                'podeNovaConversa' => $this->podeIniciarNovaConversa($usuario),
                'contasNovaConversa' => (new MetaConta())->listarPorUsuario($usuario)
            ]
        );
    }


    public function duplicadas()
    {
        Auth::admin();
        $this->jsonResponse(['ok'=>true, 'duplicadas'=>$this->conversaModel->listarDuplicadasNormalizadas()]);
    }

    public function unificarDuplicadas()
    {
        Auth::admin();
        Csrf::exigirPost();
        $resultado = $this->conversaModel->unificarDuplicadas(
            (int) ($_POST['cliente_id'] ?? 0),
            (int) ($_POST['meta_id'] ?? 0),
            (string) ($_POST['numero'] ?? '')
        );
        $this->jsonResponse(['ok'=>!empty($resultado['sucesso']), 'message'=>$resultado['mensagem'] ?? 'Processado.']);
    }

    private function jsonResponse(array $payload, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
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
                    $messageId ? 'aguardando_confirmacao' : 'erro',

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

    private function usuarioPodeAcessarConversas($usuario)
    {
        if(!$usuario){
            return false;
        }

        return (
            ($usuario['nivel'] ?? null) === 'admin'
            ||
            Auth::nivelCliente($usuario['nivel'] ?? null)
        );
    }

    private function podeGerenciarConversas($usuario)
    {
        return in_array(
            $usuario['nivel'] ?? null,
            ['admin', 'cliente_admin', 'cliente'],
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
                $usuario['CLI_ID'],
                $usuario
            );

        $ultimaBanco =
            $dados['ultima']
            ?? '';

        $statusMensagens = [];
        $conversaId = (int) ($_GET['conversa_id'] ?? 0);
        if($conversaId > 0 && $this->conversaModel->buscarAcessivel($conversaId, $usuario['CLI_ID'], $usuario)){
            foreach($this->conversaModel->listarStatusMensagens($conversaId) as $mensagem){
                $visual = MensagemStatusService::apresentacao($mensagem['MSG_Status'] ?? null, $mensagem['MSG_CodigoErro'] ?? null, $mensagem['MSG_MensagemErro'] ?? null, $mensagem['MSG_FalhouEm'] ?? null);
                if(!$visual) continue;
                $statusMensagens[] = ['id'=>(int)$mensagem['MSG_ID'], 'status'=>$visual['status'], 'icone'=>$visual['icone'], 'classe'=>$visual['classe'], 'tooltip'=>$visual['tooltip']];
            }
        }

        echo json_encode([
            'atualizar' =>
                $ultimaBanco != $ultimaLocal,

            'ultima' =>
                $ultimaBanco,
            'statuses' => $statusMensagens
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

        require __DIR__ . '/../Views/conversas/partials/mensagens.php';
    }

    public function marcarLidaAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'){
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Método inválido.'
            ]);
            return;
        }

        if(!$this->validarCsrfAjax()){
            return;
        }

        $usuario = Auth::usuario();
        $id = $_POST['id'] ?? null;

        if(!$id){
            http_response_code(400);
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

        $this->conversaModel->marcarComoLida(
            $id,
            $usuario['CLI_ID'],
            $usuario
        );

        echo json_encode([
            'sucesso' => true
        ]);
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
                    $messageId ? 'aguardando_confirmacao' : 'erro',

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


    public function templatesAprovadosAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        $usuario = Auth::usuario();
        $metaId = (int) ($_GET['meta_id'] ?? 0);

        if(!(new MetaConta())->buscarPorUsuario($metaId, $usuario)){
            http_response_code(403);
            echo json_encode(['sucesso' => false, 'erro' => 'Conta Meta não permitida.']);
            return;
        }

        echo json_encode([
            'sucesso' => true,
            'templates' => (new TemplateMeta())->listarAprovadosParaEnvioPorUsuarioConta($usuario, $metaId)
        ]);
    }


    public function buscarContatosAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        $usuario = Auth::usuario();
        $metaId = (int) ($_GET['meta_id'] ?? 0);
        $termo = trim((string) ($_GET['q'] ?? ''));
        $pagina = max(1, (int) ($_GET['page'] ?? 1));
        $limite = 20;

        if(!(new MetaConta())->buscarPorUsuario($metaId, $usuario)){
            http_response_code(403);
            echo json_encode(['sucesso' => false, 'erro' => 'Conta Meta não permitida.']);
            return;
        }

        if(mb_strlen($termo, 'UTF-8') < 2){
            echo json_encode(['sucesso' => true, 'results' => [], 'pagination' => ['more' => false]]);
            return;
        }

        $contatos = (new Contato())->pesquisarPorUsuarioMeta($usuario, $metaId, $termo, $limite + 1, $pagina);
        $temMais = count($contatos) > $limite;
        $contatos = array_slice($contatos, 0, $limite);

        $results = array_map(function($contato){
            $telefone = $this->formatarTelefoneContato($contato['CON_Telefone'] ?? '');
            $nome = $contato['CON_Nome'] ?: $contato['CON_Telefone'];
            return [
                'id' => (int) $contato['CON_ID'],
                'nome' => $contato['CON_Nome'],
                'telefone' => $contato['CON_Telefone'],
                'telefone_formatado' => $telefone,
                'text' => trim($nome . ' — ' . ($telefone ?: ($contato['CON_Telefone'] ?? '')))
            ];
        }, $contatos);

        echo json_encode([
            'sucesso' => true,
            'results' => $results,
            'pagination' => ['more' => $temMais]
        ]);
    }

    private function formatarTelefoneContato($telefone)
    {
        $numero = preg_replace('/\D/', '', (string) $telefone);
        if(substr($numero, 0, 2) === '55'){
            $numero = substr($numero, 2);
        }
        if(strlen($numero) === 11){
            return '(' . substr($numero, 0, 2) . ') ' . substr($numero, 2, 5) . '-' . substr($numero, 7);
        }
        if(strlen($numero) === 10){
            return '(' . substr($numero, 0, 2) . ') ' . substr($numero, 2, 4) . '-' . substr($numero, 6);
        }
        return $telefone;
    }

    public function iniciarPorTemplateAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        if(!$this->validarCsrfAjax()){
            return;
        }

        try{
            $servico = new ConversaTemplateService();
            $resultado = $servico->enviar(Auth::usuario(), [
                'meta_id' => $_POST['meta_id'] ?? 0,
                'template_id' => $_POST['template_id'] ?? 0,
                'telefone' => $_POST['telefone'] ?? '',
                'nome' => $_POST['nome'] ?? '',
                'variaveis' => $_POST['variaveis'] ?? [],
                'contato_id' => $_POST['contato_id'] ?? 0
            ]);

            echo json_encode($resultado);
        }catch(\Exception $e){
            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ]);
        }
    }

    private function podeIniciarNovaConversa($usuario)
    {
        if(!$this->podeGerenciarConversas($usuario)){
            return false;
        }

        return !empty(Auth::idsContasMetaPermitidas($usuario));
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
                $usuario['CLI_ID'],
                $usuario
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
            $responsavelId,
            $usuario
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
