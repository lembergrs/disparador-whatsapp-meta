<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\MetaConta;
use Models\Cliente;
use Services\EmbeddedSignupFlowService;
use Services\EmbeddedSignupAttemptCoordinator;
use Exception;

class ConfiguracaoController extends Controller
{
    private $metaContaModel;
    private $clienteModel;

    public function __construct()
    {
        Auth::clienteAdmin();

        $this->metaContaModel =
            new MetaConta();

        $this->clienteModel =
            new Cliente();
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

            $this->redirect('configuracao/meta');
        }

        return $dados;
    }

    public function salvarAutoResposta()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        if(
            !in_array(
                $usuario['nivel'] ?? null,
                ['cliente', 'cliente_admin'],
                true
            )
        ){
            die('Acesso negado');
        }

        if(!$this->metaContaModel->colunasAutoRespostaExistem()){
            Session::flash(
                'error',
                'A configuração de auto resposta ainda não está disponível. Entre em contato com o suporte.'
            );

            $this->redirect('configuracao/meta');
        }

        $contaId =
            (int) ($_POST['conta_id'] ?? 0);

        $dados =
            $this->prepararDadosAutoResposta(
                $_POST
            );

        $atualizou =
            $this->metaContaModel->atualizarAutoRespostaPorCliente(
                $contaId,
                (int) $usuario['CLI_ID'],
                $dados
            );

        Session::flash(
            $atualizou ? 'success' : 'error',
            $atualizou
                ? 'Auto resposta atualizada com sucesso.'
                : 'Conta Meta não encontrada para o seu cliente.'
        );

        $this->redirect('configuracao/meta');
    }




    private function renderMetaCallbackPage($ok, $message, $requestId = null)
    {
        $type = $ok ? 'success' : 'error';
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeRequestId = htmlspecialchars((string) $requestId, ENT_QUOTES, 'UTF-8');
        $base = htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8');
        echo "<!doctype html><html lang='pt-BR'><head><meta charset='utf-8'><title>Conexão WhatsApp</title><style>body{font-family:Arial,sans-serif;margin:40px;background:#f6f7fb}.box{max-width:680px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08)}.success{color:#167c3b}.error{color:#b42318}.muted{color:#667085}</style></head><body><div class='box'><h1 class='{$type}'>" . ($ok ? 'Conexão concluída' : 'Não foi possível concluir') . "</h1><p>{$safeMessage}</p>";
        if($safeRequestId !== ''){
            echo "<p class='muted'>Código de diagnóstico: <strong>{$safeRequestId}</strong></p>";
        }
        echo "<p>Você já pode fechar esta aba e voltar ao Disparador.net.</p><p><a href='{$base}/index.php?url=configuracao/meta'>Voltar à configuração</a></p></div><script>(function(){var msg={type:'DISPARADOR_META_EMBEDDED_SIGNUP_CALLBACK',ok:" . ($ok ? 'true' : 'false') . ",requestId:" . json_encode($requestId) . "};try{if(window.opener && !window.opener.closed){window.opener.postMessage(msg," . json_encode(BASE_URL) . ");}}catch(e){} if(msg.ok){setTimeout(function(){try{window.close();}catch(e){}},2500);}})();</script></body></html>";
        exit;
    }

    private function jsonResponse(array $payload, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function sanitizeMetaMessage($message)
    {
        $message = (string) $message;
        $message = preg_replace('/(access_token|client_secret|app_secret|code)=([^&\s]+)/i', '$1=[removido]', $message);
        $message = preg_replace('/EA[A-Za-z0-9_-]{20,}/', '[token-removido]', $message);
        return mb_substr($message, 0, 500);
    }

    private function requestId()
    {
        return 'es_' . bin2hex(random_bytes(12));
    }

    private function validarConfiguracaoEmbeddedSignup()
    {
        $required = [
            'META_APP_ID' => META_APP_ID,
            'META_APP_SECRET' => META_APP_SECRET,
            'META_CONFIGURATION_ID' => META_CONFIGURATION_ID,
            'META_GRAPH_VERSION' => META_GRAPH_VERSION,
            'META_EMBEDDED_SIGNUP_REDIRECT_URI' => META_EMBEDDED_SIGNUP_REDIRECT_URI,
            'META_VERIFY_TOKEN' => defined('META_VERIFY_TOKEN') ? META_VERIFY_TOKEN : '',
            'BASE_URL' => BASE_URL
        ];

        $missing = [];
        foreach($required as $key => $value){
            if(trim((string) $value) === ''){
                $missing[] = $key;
            }
        }

        if(!empty($missing)){
            throw new Exception('Configuração incompleta: ' . implode(', ', $missing));
        }

        if(strpos((string) META_EMBEDDED_SIGNUP_REDIRECT_URI, 'https://') !== 0){
            throw new Exception('META_EMBEDDED_SIGNUP_REDIRECT_URI deve usar HTTPS e ser exatamente a URL cadastrada na Meta.');
        }
    }

    private function embeddedSessionKey($state)
    {
        return 'meta_embedded_signup_' . hash('sha256', (string) $state);
    }

    private function getTentativaEmbedded($state)
    {
        $key = $this->embeddedSessionKey($state);
        $tentativa = $_SESSION[$key] ?? null;
        if(!is_array($tentativa)){
            return null;
        }
        if(($tentativa['expires_at'] ?? 0) < time()){
            unset($_SESSION[$key]);
            return null;
        }
        return $tentativa;
    }

    private function salvarTentativaEmbedded($state, array $tentativa)
    {
        $_SESSION[$this->embeddedSessionKey($state)] = $tentativa;
    }

    private function validarTentativaEmbeddedCallback($state, $clienteId)
    {
        $tentativa = $this->getTentativaEmbedded($state);
        if(!$tentativa || (int) ($tentativa['cliente_id'] ?? 0) !== (int) $clienteId){
            throw new Exception('Tentativa expirada ou não pertence ao cliente autenticado.');
        }
        if(!empty($tentativa['used_at'])){
            throw new Exception('Este retorno da Meta já foi utilizado.');
        }
        return $tentativa;
    }

    private function aguardarTentativaEmbeddedParaCallback($state, $clienteId)
    {
        $coordenador = new EmbeddedSignupAttemptCoordinator();
        $tentativa = $coordenador->aguardarFinish(function() use ($state, $clienteId){
            return $this->validarTentativaEmbeddedCallback($state, $clienteId);
        }, 3000, 100);

        if(!$tentativa){
            throw new Exception('Tentativa expirada ou não pertence ao cliente autenticado.');
        }

        return $tentativa;
    }

    private function marcarTentativaEmbeddedUsada($state, array $tentativa)
    {
        $key = $this->embeddedSessionKey($state);
        $tentativaAtual = $this->getTentativaEmbedded($state);
        if(!$tentativaAtual || !empty($tentativaAtual['used_at'])){
            throw new Exception('Este retorno da Meta já foi utilizado.');
        }

        $tentativaAtual['used_at'] = time();
        $_SESSION[$key] = $tentativaAtual;
        return $tentativaAtual;
    }

    private function extrairSessionInfoIds(array $payload)
    {
        $data = $payload['data'] ?? $payload;
        $ids = [];
        foreach(['waba_id','phone_number_id','business_id'] as $field){
            if(!empty($data[$field]) && preg_match('/^[0-9]{5,30}$/', (string) $data[$field])){
                $ids[$field] = (string) $data[$field];
            }
        }
        $ids['raw_keys'] = array_values(array_intersect(array_keys($data), ['waba_id','phone_number_id','business_id','business_account_id']));
        return $ids;
    }

    public function iniciarEmbeddedSignup()
    {
        \Core\Csrf::exigirPost();
        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $requestId = $this->requestId();

        try{
            $this->validarConfiguracaoEmbeddedSignup();
            $limite = $this->metaContaModel->avaliarLimiteNumerosPorCliente($clienteId);
            if(empty($limite['permitido'])){
                throw new Exception($limite['mensagem'] ?? 'Limite de números do plano atingido.');
            }

            $state = bin2hex(random_bytes(32));
            $tentativa = [
                'request_id' => $requestId,
                'cliente_id' => $clienteId,
                'created_at' => time(),
                'expires_at' => time() + 1800,
                'finish' => null,
                'used_at' => null
            ];
            $this->salvarTentativaEmbedded($state, $tentativa);

            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'etapa'=>'inicio','request_id'=>$requestId,'resultado'=>'ok']);

            $this->jsonResponse([
                'ok' => true,
                'requestId' => $requestId,
                'state' => $state,
                'appId' => META_APP_ID,
                'configurationId' => META_CONFIGURATION_ID,
                'redirectUri' => META_EMBEDDED_SIGNUP_REDIRECT_URI,
                'graphVersion' => META_GRAPH_VERSION
            ]);
        }catch(Exception $e){
            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'etapa'=>'inicio','request_id'=>$requestId,'erro'=>$this->sanitizeMetaMessage($e->getMessage()),'resultado'=>'erro']);
            $this->jsonResponse(['ok'=>false,'requestId'=>$requestId,'message'=>$this->sanitizeMetaMessage($e->getMessage())], 400);
        }
    }

    public function registrarEmbeddedSignupFinish()
    {
        \Core\Csrf::exigirPost();
        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $state = (string) ($_POST['state'] ?? '');
        $payload = json_decode((string) ($_POST['session_info'] ?? ''), true);

        if(!is_array($payload) || ($payload['event'] ?? '') !== 'FINISH'){
            $this->jsonResponse(['ok'=>false,'message'=>'Evento FINISH inválido.'], 422);
        }

        $tentativa = $this->getTentativaEmbedded($state);
        if(!$tentativa || (int) ($tentativa['cliente_id'] ?? 0) !== $clienteId){
            $this->jsonResponse(['ok'=>false,'message'=>'Tentativa expirada ou inválida.'], 403);
        }

        if(!empty($tentativa['used_at'])){
            $this->jsonResponse(['ok'=>false,'message'=>'Tentativa já consumida pelo callback.'], 409);
        }

        $ids = $this->extrairSessionInfoIds($payload);
        $tentativa['finish'] = [
            'received_at' => time(),
            'ids' => $ids
        ];
        $this->salvarTentativaEmbedded($state, $tentativa);

        $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'etapa'=>'finish','request_id'=>$tentativa['request_id'],'waba_id'=>$ids['waba_id'] ?? null,'phone_number_id'=>$ids['phone_number_id'] ?? null,'resultado'=>'ok']);
        $this->jsonResponse(['ok'=>true,'requestId'=>$tentativa['request_id']]);
    }


    private function diretorioLogMeta()
    {
        $diretorioLog = function_exists('diretorioLogsProjeto')
            ? diretorioLogsProjeto()
            : dirname(__DIR__, 2) . '/storage/logs';

        if(!is_dir($diretorioLog)){
            mkdir($diretorioLog, 0770, true);
        }

        return $diretorioLog;
    }

    private function logMetaEmbeddedSignup(array $dados)
    {
        unset($dados['access_token'], $dados['token'], $dados['app_secret'], $dados['client_secret']);

        error_log(
            json_encode($dados, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            3,
            $this->diretorioLogMeta() . '/meta-embedded-signup-callback.log'
        );
    }

    private function embeddedSignupFlowService()
    {
        return new EmbeddedSignupFlowService(function($endpoint, array $params = [], $accessToken = null, $method = 'GET'){
            return $this->graphRequest($endpoint, $params, $accessToken, $method);
        }, META_APP_ID);
    }

    private function graphRequest($endpoint, array $params = [], $accessToken = null, $method = 'GET')
    {
        $version = trim((string) META_GRAPH_VERSION);
        $version = $version !== '' ? ltrim($version, '/') : 'v20.0';
        $url = 'https://graph.facebook.com/' . $version . '/' . ltrim($endpoint, '/');

        if($accessToken !== null){
            $params['access_token'] = $accessToken;
        }

        $method = strtoupper((string) $method);
        if(!in_array($method, ['GET', 'POST'], true)){
            throw new Exception('Método HTTP não suportado para Graph API.');
        }

        if($method === 'GET' && !empty($params)){
            $url .= '?' . http_build_query($params);
        }

        $curl = curl_init($url);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ];

        if($method === 'POST'){
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($params);
        }

        curl_setopt_array($curl, $curlOptions);

        $body = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if($body === false){
            throw new Exception('Erro de comunicação com a Meta: ' . $curlError);
        }

        $json = json_decode($body, true);

        if(!is_array($json)){
            throw new Exception('Resposta inválida da Meta com HTTP ' . $httpCode . '.');
        }

        if($httpCode >= 400 || isset($json['error'])){
            throw new Exception('Erro da Meta HTTP ' . $httpCode . ': ' . $this->sanitizeMetaMessage(($json['error']['message'] ?? ($json['error']['code'] ?? 'erro_meta'))));
        }

        return $json;
    }

    private function trocarCodePorToken($code)
    {
        if(META_APP_ID === '' || META_APP_SECRET === '' || META_EMBEDDED_SIGNUP_REDIRECT_URI === ''){
            throw new Exception('Configuração META_APP_ID, META_APP_SECRET ou META_EMBEDDED_SIGNUP_REDIRECT_URI ausente.');
        }

        $resposta = $this->graphRequest('oauth/access_token', [
            'client_id' => META_APP_ID,
            'client_secret' => META_APP_SECRET,
            'redirect_uri' => META_EMBEDDED_SIGNUP_REDIRECT_URI,
            'code' => $code
        ]);

        if(empty($resposta['access_token'])){
            throw new Exception('A Meta não retornou access_token na troca do code.');
        }

        return $resposta['access_token'];
    }

    private function validarDebugToken($accessToken)
    {
        $appToken = META_APP_ID . '|' . META_APP_SECRET;
        $debug = $this->graphRequest('debug_token', [
            'input_token' => $accessToken
        ], $appToken);

        return $this->embeddedSignupFlowService()->validarDebugToken($debug);
    }

    private function extrairWabaIdsDoDebugToken($accessToken, array $debug = null)
    {
        $debug = $debug ?: $this->validarDebugToken($accessToken);

        return $this->embeddedSignupFlowService()->extrairWabaIdsDoDebugToken($debug);
    }

    private function buscarDadosWhatsApp($accessToken, array $tentativa)
    {
        $debug = $this->validarDebugToken($accessToken);
        [$wabaIds, $businessIdFallback] = $this->extrairWabaIdsDoDebugToken($accessToken, $debug);
        $finishIds = $tentativa['finish']['ids'] ?? [];
        $wabaIdSelecionado = $finishIds['waba_id'] ?? null;
        $phoneIdSelecionado = $finishIds['phone_number_id'] ?? null;

        if($wabaIdSelecionado){
            if(!in_array((string) $wabaIdSelecionado, $wabaIds, true)){
                throw new Exception('WABA selecionada não está contemplada nas permissões concedidas.');
            }
            $wabaIds = [$wabaIdSelecionado];
        }elseif(count($wabaIds) !== 1){
            throw new Exception('A Meta retornou múltiplas WABAs possíveis. Refaça o Cadastro Incorporado para selecionar uma WABA específica.');
        }

        $wabaId = (string) $wabaIds[0];
        $waba = $this->graphRequest($wabaId, [
            'fields' => 'id,name,business{id},phone_numbers{id,display_phone_number,verified_name,quality_rating,code_verification_status,name_status,status}'
        ], $accessToken);

        $telefones = $waba['phone_numbers']['data'] ?? [];
        if($phoneIdSelecionado){
            $telefones = array_values(array_filter($telefones, function($telefone) use ($phoneIdSelecionado){
                return (string) ($telefone['id'] ?? '') === (string) $phoneIdSelecionado;
            }));
            if(count($telefones) !== 1){
                throw new Exception('Phone Number selecionado não pertence à WABA autorizada.');
            }
        }elseif(count($telefones) !== 1){
            throw new Exception('A Meta retornou múltiplos números possíveis. Refaça o Cadastro Incorporado para selecionar um número específico.');
        }

        $telefone = $telefones[0];
        return [
            'business_id' => $finishIds['business_id'] ?? ($waba['business']['id'] ?? $businessIdFallback),
            'waba_id' => $waba['id'] ?? $wabaId,
            'waba_name' => $waba['name'] ?? null,
            'phone_number_id' => $telefone['id'],
            'numero' => $telefone['display_phone_number'] ?? '',
            'display_name' => $telefone['verified_name'] ?? ($waba['name'] ?? null),
            'quality_rating' => $telefone['quality_rating'] ?? null,
            'code_verification_status' => $telefone['code_verification_status'] ?? null,
            'name_status' => $telefone['name_status'] ?? null,
            'operational_status' => $telefone['status'] ?? null
        ];
    }

    private function assinarAppNaWaba($wabaId, $accessToken)
    {
        return $this->embeddedSignupFlowService()->assinarAppNaWaba($wabaId, $accessToken);
    }

    public function metaCallback()
    {
        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);

        $this->logMetaEmbeddedSignup([
            'data' => date('Y-m-d H:i:s'),
            'cliente_id' => $clienteId,
            'has_code' => !empty($_GET['code']),
            'error' => $_GET['error'] ?? null,
            'error_reason' => $_GET['error_reason'] ?? null,
            'error_description' => $_GET['error_description'] ?? null,
            'state_present' => isset($_GET['state'])
        ]);

        if(!empty($_GET['error'])){
            Session::flash(
                'error',
                'A Meta não concluiu a conexão do número. Tente novamente ou verifique a configuração do Cadastro Incorporado.'
            );

            $this->redirect('configuracao/meta');
        }

        if(empty($_GET['code'])){
            Session::flash(
                'warning',
                'Retorno da Meta recebido, mas nenhum código de autorização foi informado. Refaça o Cadastro Incorporado e conclua todas as etapas.'
            );

            $this->redirect('configuracao/meta');
        }

        $tentativa = null;

        try{
            $stateCallback = (string) ($_GET['state'] ?? '');
            $tentativa = $this->aguardarTentativaEmbeddedParaCallback($stateCallback, $clienteId);
            $tentativa = $this->marcarTentativaEmbeddedUsada($stateCallback, $tentativa);
            $accessToken = $this->trocarCodePorToken((string) $_GET['code']);
            $dadosWhatsApp = $this->buscarDadosWhatsApp($accessToken, $tentativa);
            $this->assinarAppNaWaba($dadosWhatsApp['waba_id'], $accessToken);
            $statusConexao = $this->embeddedSignupFlowService()->definirStatusConexao($dadosWhatsApp);

            $contaId = $this->metaContaModel->salvarOuAtualizarEmbeddedSignup([
                'cliente' => $clienteId,
                'nome' => $dadosWhatsApp['display_name'] ?: ($dadosWhatsApp['waba_name'] ?: 'WhatsApp Cloud API'),
                'phone_number_id' => $dadosWhatsApp['phone_number_id'],
                'waba_id' => $dadosWhatsApp['waba_id'],
                'token' => $accessToken,
                'url_base' => 'https://graph.facebook.com/' . ltrim((string) META_GRAPH_VERSION, '/') . '/',
                'numero' => $dadosWhatsApp['numero'],
                'webhook_verify_token' => defined('META_VERIFY_TOKEN') ? META_VERIFY_TOKEN : '',
                'business_id' => $dadosWhatsApp['business_id'],
                'display_name' => $dadosWhatsApp['display_name'],
                'status' => $statusConexao,
                'quality_rating' => $dadosWhatsApp['quality_rating'] ?? null,
                'code_verification_status' => $dadosWhatsApp['code_verification_status'] ?? null,
                'name_status' => $dadosWhatsApp['name_status'] ?? null,
                'operational_status' => $dadosWhatsApp['operational_status'] ?? null
            ]);

            if(!$contaId){
                throw new Exception('Falha ao salvar conta Meta no banco.');
            }

            if($statusConexao === 'conectada'){
                $this->clienteModel->iniciarTrialSePendente($clienteId);

                if(isset($_SESSION['usuario'])){
                    $_SESSION['usuario']['CLI_DataLiberacao'] = $_SESSION['usuario']['CLI_DataLiberacao'] ?? date('Y-m-d H:i:s');
                }
            }

            $this->logMetaEmbeddedSignup([
                'data' => date('Y-m-d H:i:s'),
                'cliente_id' => $clienteId,
                'conta_id' => $contaId,
                'waba_id' => $dadosWhatsApp['waba_id'],
                'phone_number_id' => $dadosWhatsApp['phone_number_id'],
                'request_id' => $tentativa['request_id'] ?? null,
                'status' => $statusConexao
            ]);

            if($statusConexao === 'conectada'){
                $this->renderMetaCallbackPage(true, 'WhatsApp conectado com sucesso. A conta já está disponível para sincronizar templates e enviar mensagens.', $tentativa['request_id'] ?? null);
            }

            $this->renderMetaCallbackPage(false, 'A autorização foi salva, mas o número requer ação adicional antes de ficar operacional. Informe o código de diagnóstico ao suporte.', $tentativa['request_id'] ?? null);
        }catch(Exception $e){
            $this->logMetaEmbeddedSignup([
                'data' => date('Y-m-d H:i:s'),
                'cliente_id' => $clienteId,
                'erro' => $e->getMessage()
            ]);

            $this->renderMetaCallbackPage(false, 'Não foi possível concluir a conexão com a Meta agora. Tente novamente e informe o código de diagnóstico ao suporte.', $tentativa['request_id'] ?? null);
        }

        $this->redirect('configuracao/meta');
    }

    public function meta()
    {
        $usuario =
            Auth::usuario();

        $contas =
            $this->metaContaModel
            ->listarPorCliente(
                $usuario['CLI_ID']
            );

        $limiteNumeros =
            $this->metaContaModel
            ->avaliarLimiteNumerosPorCliente(
                $usuario['CLI_ID']
            );

        $this->view(
            'configuracao/meta',
            [
                'titulo' => 'Números WhatsApp',
                'contas' => $contas,
                'limiteNumeros' => $limiteNumeros
            ]
        );
    }
}
