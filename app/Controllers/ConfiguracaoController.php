<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\MetaConta;
use Models\Cliente;
use Models\MetaEmbeddedSignupAttempt;
use Services\EmbeddedSignupFlowService;
use Services\MetaService;
use Services\EmbeddedSignupAttemptCoordinator;
use Services\EmbeddedSignupOnboardingMode;
use Services\MetaCoexistenceEligibility;
use Services\AnalyticsService;
use Services\EventoNotificacao;
use Services\CanalNotificacao;
use Services\NotificacaoService;
use Exception;

class ConfiguracaoController extends Controller
{
    private $metaContaModel;
    private $clienteModel;
    private $embeddedAttemptModel;

    public function __construct()
    {
        Auth::clienteAdmin();

        $this->metaContaModel =
            new MetaConta();

        $this->clienteModel =
            new Cliente();

        $this->embeddedAttemptModel =
            new MetaEmbeddedSignupAttempt();
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
        $gtmPartial = __DIR__ . '/../Views/partials/google_tag_manager.php';
        ob_start(); $googleTagManagerSection = 'head'; require $gtmPartial; $gtmHead = ob_get_clean();
        ob_start(); $googleTagManagerSection = 'body'; require $gtmPartial; $gtmBody = ob_get_clean();
        echo "<!doctype html><html lang='pt-BR'><head>{$gtmHead}<meta charset='utf-8'><title>Conexão WhatsApp</title><style>body{font-family:Arial,sans-serif;margin:40px;background:#f6f7fb}.box{max-width:680px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08)}.success{color:#167c3b}.error{color:#b42318}.muted{color:#667085}</style></head><body>{$gtmBody}<div class='box'><h1 class='{$type}'>" . ($ok ? 'Conexão concluída' : 'Não foi possível concluir') . "</h1><p>{$safeMessage}</p>";
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

    private function coexistenceEligibility()
    {
        return new MetaCoexistenceEligibility(
            META_COEXISTENCE_ENABLED,
            META_COEXISTENCE_TEST_CLIENT_IDS
        );
    }

    private function exigirCoexistenceDisponivel($clienteId, $onboardingType)
    {
        if(
            $onboardingType === EmbeddedSignupOnboardingMode::COEXISTENCE
            && !$this->coexistenceEligibility()->availableForClient($clienteId)
        ){
            throw new Exception('Esta modalidade de conexão não está disponível para este cliente.');
        }
    }

    private function avaliarPermissaoConexao($clienteId, $ignorarContaId = null)
    {
        $numerosAtivos = $this->metaContaModel->contarAtivasPorCliente($clienteId, $ignorarContaId);
        $preTrialElegivel = Auth::podeConectarPrimeiroNumero(
            $clienteId,
            $numerosAtivos
        );

        $limite = $this->metaContaModel->avaliarLimiteNumerosPorCliente(
            $clienteId,
            $ignorarContaId,
            $preTrialElegivel
        );

        if(
            empty($limite['permitido'])
            && empty($ignorarContaId)
            && $this->metaContaModel->temContaDesconectadaPorCliente($clienteId)
        ){
            $limite['permitido'] = true;
            $limite['reconexao'] = true;
            $limite['mensagem'] = null;
        }

        return $limite;
    }

    private function exigirPermissaoConexao($clienteId, $ignorarContaId = null)
    {
        $limite = $this->avaliarPermissaoConexao($clienteId, $ignorarContaId);

        if(empty($limite['permitido'])){
            throw new Exception($limite['mensagem'] ?? 'Limite de números do plano atingido.');
        }

        return $limite;
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

    private function getTentativaEmbedded($state, $clienteId)
    {
        return $this->embeddedAttemptModel->buscarValida($state, $clienteId);
    }

    private function salvarTentativaEmbedded($state, array $tentativa)
    {
        return $this->embeddedAttemptModel->criar(
            $state,
            $tentativa['cliente_id'],
            $tentativa['request_id'],
            max(1, (int) (($tentativa['expires_at'] ?? time()) - time())),
            $tentativa['onboarding_type'] ?? EmbeddedSignupOnboardingMode::TRADITIONAL
        );
    }

    private function validarTentativaEmbeddedCallback($state, $clienteId)
    {
        $tentativa = $this->getTentativaEmbedded($state, $clienteId);
        if(!$tentativa){
            throw new Exception('Tentativa expirada, já utilizada ou não pertence ao cliente autenticado.');
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

    private function marcarTentativaEmbeddedUsada($state, array $tentativa, $clienteId)
    {
        $tentativaConsumida = $this->embeddedAttemptModel->consumir($state, $clienteId);
        if(!$tentativaConsumida){
            throw new Exception('Este retorno da Meta já foi utilizado.');
        }

        return $tentativaConsumida;
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
            $coexistenceDisponivel = $this->coexistenceEligibility()->availableForClient($clienteId);
            if($coexistenceDisponivel && !$this->metaContaModel->colunasCoexistenceExistem()){
                throw new Exception('A migration de infraestrutura Coexistence ainda não foi aplicada.');
            }
            $this->validarConfiguracaoEmbeddedSignup();
            $this->exigirPermissaoConexao($clienteId);

            $state = bin2hex(random_bytes(32));
            $tentativa = [
                'request_id' => $requestId,
                'cliente_id' => $clienteId,
                'onboarding_type' => EmbeddedSignupOnboardingMode::TRADITIONAL,
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
                'graphVersion' => META_GRAPH_VERSION,
                'coexistenceAvailable' => $coexistenceDisponivel
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

        $tentativa = $this->getTentativaEmbedded($state, $clienteId);
        if(!$tentativa || (int) ($tentativa['cliente_id'] ?? 0) !== $clienteId){
            $this->jsonResponse(['ok'=>false,'message'=>'Tentativa expirada ou inválida.'], 403);
        }

        if(!empty($tentativa['used_at'])){
            $this->jsonResponse(['ok'=>false,'message'=>'Tentativa já consumida pelo callback.'], 409);
        }

        try{
            $onboardingType = EmbeddedSignupOnboardingMode::fromFinishEvent($payload['event'] ?? null);
            $this->exigirCoexistenceDisponivel($clienteId, $onboardingType);
        }catch(\InvalidArgumentException $e){
            $this->jsonResponse(['ok'=>false,'message'=>'Evento de conclusão incompatível com o onboarding Meta.'], 422);
        }catch(Exception $e){
            $this->jsonResponse(['ok'=>false,'message'=>$this->sanitizeMetaMessage($e->getMessage())], 403);
        }

        $ids = $this->extrairSessionInfoIds($payload);
        $finish = [
            'received_at' => time(),
            'ids' => $ids
        ];

        if(!$this->embeddedAttemptModel->salvarFinish($state, $clienteId, $finish, $onboardingType)){
            $this->jsonResponse(['ok'=>false,'message'=>'Tentativa já consumida pelo callback.'], 409);
        }

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
        $rateLimitHeaders = [];
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function($curl, $headerLine) use (&$rateLimitHeaders){
                $this->captureMetaRateLimitHeader($rateLimitHeaders, $headerLine);
                return strlen($headerLine);
            }
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
            $diagnostic = $this->buildMetaRateLimitDiagnostic($endpoint, $httpCode, $json, $rateLimitHeaders);
            if($diagnostic !== null){
                $this->logMetaEmbeddedSignup($diagnostic);
            }
            throw new Exception('Erro da Meta HTTP ' . $httpCode . ': ' . $this->sanitizeMetaMessage(($json['error']['message'] ?? ($json['error']['code'] ?? 'erro_meta'))));
        }

        return $json;
    }

    private function captureMetaRateLimitHeader(array &$headers, $headerLine)
    {
        if(strpos((string) $headerLine, ':') === false){
            return;
        }

        [$rawName, $rawValue] = explode(':', (string) $headerLine, 2);
        $name = strtolower(trim($rawName));
        $canonicalNames = [
            'x-app-usage' => 'X-App-Usage',
            'x-business-use-case-usage' => 'X-Business-Use-Case-Usage',
            'retry-after' => 'Retry-After'
        ];
        if(!isset($canonicalNames[$name])){
            return;
        }

        $value = trim($rawValue);
        if($name === 'retry-after'){
            if(!preg_match('/^(?:[0-9]{1,10}|[A-Za-z]{3}, [0-9]{2} [A-Za-z]{3} [0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2} GMT)$/D', $value)){
                return;
            }
        }else{
            $decoded = json_decode($value, true);
            if(!is_array($decoded)){
                return;
            }
            $value = json_encode($this->sanitizeMetaUsageHeaderValue($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $headers[$canonicalNames[$name]] = mb_substr($value, 0, 2000, 'UTF-8');
    }

    private function sanitizeMetaUsageHeaderValue(array $value)
    {
        $safe = [];
        foreach($value as $key => $item){
            if(is_string($key) && preg_match('/token|secret|authorization|cookie/i', $key)){
                continue;
            }
            $safe[$key] = is_array($item) ? $this->sanitizeMetaUsageHeaderValue($item) : $item;
        }
        return $safe;
    }

    private function buildMetaRateLimitDiagnostic($endpoint, $httpCode, array $json, array $headers)
    {
        $error = isset($json['error']) && is_array($json['error']) ? $json['error'] : [];
        $metaCode = isset($error['code']) && is_numeric($error['code']) ? (int) $error['code'] : null;
        $message = strtolower((string) ($error['message'] ?? ''));
        $rateLimitRelated = in_array($metaCode, [4, 80007], true)
            || strpos($message, 'rate limit') !== false
            || strpos($message, 'request limit') !== false
            || strpos($message, 'too many request') !== false;

        if(empty($headers) && !$rateLimitRelated){
            return null;
        }

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'etapa' => 'meta_graph_rate_limit',
            'http_code' => (int) $httpCode,
            'meta_error_code' => $metaCode,
            'endpoint' => strtok(ltrim((string) $endpoint, '/'), '?'),
            'X-App-Usage' => $headers['X-App-Usage'] ?? null,
            'X-Business-Use-Case-Usage' => $headers['X-Business-Use-Case-Usage'] ?? null,
            'Retry-After' => $headers['Retry-After'] ?? null
        ];
    }

    private function trocarCodePorToken($code, $usarRedirectUri = true)
    {
        if(META_APP_ID === '' || META_APP_SECRET === ''){
            throw new Exception('Configuração META_APP_ID ou META_APP_SECRET ausente.');
        }

        $parametros = [
            'client_id' => META_APP_ID,
            'client_secret' => META_APP_SECRET,
            'code' => $code
        ];

        if($usarRedirectUri){
            if(META_EMBEDDED_SIGNUP_REDIRECT_URI === ''){
                throw new Exception('Configuração META_EMBEDDED_SIGNUP_REDIRECT_URI ausente.');
            }
            $parametros['redirect_uri'] = META_EMBEDDED_SIGNUP_REDIRECT_URI;
        }

        $resposta = $this->graphRequest('oauth/access_token', $parametros);

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
            'fields' => 'id,name,phone_numbers{id,display_phone_number,verified_name,quality_rating,code_verification_status,name_status,status,platform_type}'
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
            'business_id' => $finishIds['business_id'] ?? $businessIdFallback,
            'waba_id' => $waba['id'] ?? $wabaId,
            'waba_name' => $waba['name'] ?? null,
            'phone_number_id' => $telefone['id'],
            'numero' => $telefone['display_phone_number'] ?? '',
            'display_name' => $telefone['verified_name'] ?? ($waba['name'] ?? null),
            'quality_rating' => $telefone['quality_rating'] ?? null,
            'code_verification_status' => $telefone['code_verification_status'] ?? null,
            'name_status' => $telefone['name_status'] ?? null,
            'operational_status' => $telefone['status'] ?? null,
            'platform_type' => $telefone['platform_type'] ?? null
        ];
    }


    private function buscarDadosPhoneNumber($phoneNumberId, $accessToken)
    {
        $contaTemporaria = [
            'MTA_ID' => 0,
            'CLI_ID' => 0,
            'MTA_PhoneNumberId' => $phoneNumberId,
            'MTA_Token' => $accessToken,
            'MTA_UrlBase' => 'https://graph.facebook.com/' . ltrim((string) META_GRAPH_VERSION, '/') . '/',
            'MTA_WabaId' => null,
            'MTA_Status' => 'conectado',
            'MTA_Ativo' => 'S'
        ];

        return MetaService::consultarDadosNumeroConta($contaTemporaria);
    }

    private function sincronizarDadosNumeroMeta(array $conta)
    {
        $service = new MetaService((int) $conta['MTA_ID'], (int) $conta['CLI_ID']);
        return $service->consultarDadosNumero();
    }

    private function registrarFalhaSincronizacaoMeta(array $conta, $requestId, Exception $e, $etapa)
    {
        $this->logMetaEmbeddedSignup([
            'data' => date('Y-m-d H:i:s'),
            'cliente_id' => $conta['CLI_ID'] ?? null,
            'conta_id' => $conta['MTA_ID'] ?? null,
            'waba_id' => $conta['MTA_WabaId'] ?? null,
            'phone_number_id' => $conta['MTA_PhoneNumberId'] ?? null,
            'request_id' => $requestId,
            'etapa' => $etapa,
            'erro' => $this->sanitizeMetaMessage($e->getMessage()),
            'resultado' => 'erro_sincronizacao'
        ]);
    }

    private function mensagemAmigavelErroMeta($mensagem)
    {
        $texto = (string) $mensagem;
        if(strpos($texto, '133010') !== false || stripos($texto, 'Account not registered') !== false){
            return 'O número ainda não concluiu o registro no WhatsApp. Informe o PIN de 6 dígitos para finalizar a conexão.';
        }
        if(strpos($texto, 'code 100') !== false || strpos($texto, 'subcode 33') !== false || stripos($texto, 'Unsupported post request') !== false){
            return 'Não foi possível acessar o número vinculado. Refaça a conexão com a Meta ou entre em contato com o suporte.';
        }
        if(stripos($texto, 'token') !== false || stripos($texto, 'OAuth') !== false){
            return 'A autorização da Meta expirou ou não possui mais acesso ao número.';
        }
        if(stripos($texto, 'Phone Number ID') !== false){
            return 'A conta não possui um Phone Number ID válido.';
        }
        if(stripos($texto, 'consultar a Meta') !== false || stripos($texto, 'timeout') !== false || stripos($texto, 'cURL') !== false || stripos($texto, 'HTTP 500') !== false){
            return 'Não foi possível consultar a Meta neste momento. Tente novamente.';
        }
        if(stripos($texto, 'pin') !== false || stripos($texto, 'payload') !== false || stripos($texto, 'registro operacional') !== false){
            return 'Não foi possível concluir o registro com esse PIN. Confira os 6 dígitos e tente novamente.';
        }
        return 'Não foi possível atualizar os dados da conta Meta.';
    }

    private function registrarPhoneNumberMeta($phoneNumberId, $pin, $accessToken)
    {
        return $this->embeddedSignupFlowService()->registrarPhoneNumber($phoneNumberId, $pin, $accessToken);
    }

    private function atualizarStatusOperacionalConta($clienteId, array $conta, array $dadosTelefone)
    {
        $statusConexao = $dadosTelefone['status'] ?? $this->embeddedSignupFlowService()->definirStatusConexao($dadosTelefone);

        $atualizou = $this->metaContaModel->atualizarStatusOperacionalEmbeddedSignup(
            (int) $conta['MTA_ID'],
            $clienteId,
            array_merge($dadosTelefone, ['status' => $statusConexao])
        );

        if($statusConexao === 'conectado'){
            if($atualizou && ($conta['MTA_Status'] ?? '') !== 'conectado' && !empty($conta['MTA_WabaId']) && !empty($conta['MTA_PhoneNumberId'])){
                AnalyticsService::registrar('connect_meta', [
                    'connection_type'=>'embedded_signup', 'first_connection'=>true, 'source_area'=>'configuration'
                ]);
            }
            $this->clienteModel->iniciarTrialSePendente($clienteId);
            $this->dispararMetaConectada($clienteId, CanalNotificacao::WHATSAPP);
        }

        return $statusConexao;
    }

    private function assinarAppNaWaba($wabaId, $accessToken)
    {
        return $this->embeddedSignupFlowService()->assinarAppNaWaba($wabaId, $accessToken);
    }

    private function processarEmbeddedSignupCode($clienteId, $state, $code, $usarRedirectUri = false)
    {
        $this->exigirPermissaoConexao($clienteId);
        $tentativa = $this->aguardarTentativaEmbeddedParaCallback($state, $clienteId);
        $onboardingType = EmbeddedSignupOnboardingMode::normalize($tentativa['onboarding_type'] ?? null);
        $this->exigirCoexistenceDisponivel($clienteId, $onboardingType);
        if($onboardingType === EmbeddedSignupOnboardingMode::COEXISTENCE && empty($tentativa['finish'])){
            throw new Exception('A conclusão do onboarding Coexistence não foi confirmada pela Meta.');
        }
        $tentativa = $this->marcarTentativaEmbeddedUsada($state, $tentativa, $clienteId);
        $accessToken = $this->trocarCodePorToken((string) $code, $usarRedirectUri);
        $dadosWhatsApp = $this->buscarDadosWhatsApp($accessToken, $tentativa);
        $this->assinarAppNaWaba($dadosWhatsApp['waba_id'], $accessToken);
        $statusConexao = $onboardingType === EmbeddedSignupOnboardingMode::COEXISTENCE
            ? $this->embeddedSignupFlowService()->definirStatusCoexistencia($dadosWhatsApp)
            : 'pendente_registro';

        $dadosPersistencia = [
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
            'onboarding_type' => $onboardingType,
            'platform_type' => $dadosWhatsApp['platform_type'] ?? null,
            'quality_rating' => $dadosWhatsApp['quality_rating'] ?? null,
            'code_verification_status' => $dadosWhatsApp['code_verification_status'] ?? null,
            'name_status' => $dadosWhatsApp['name_status'] ?? null,
            'operational_status' => $dadosWhatsApp['operational_status'] ?? null
        ];

        $contaExistente = $this->metaContaModel->buscarPorClienteWabaPhone(
            $clienteId,
            $dadosWhatsApp['waba_id'],
            $dadosWhatsApp['phone_number_id']
        );
        $contaExistenteId = (int) ($contaExistente['MTA_ID'] ?? 0);

        $contaId = $this->metaContaModel->salvarOuAtualizarEmbeddedSignupComBloqueio(
            $dadosPersistencia,
            function() use ($clienteId, $contaExistenteId){
                $this->exigirPermissaoConexao($clienteId, $contaExistenteId ?: null);
            }
        );

        if(!$contaId){
            throw new Exception('Falha ao salvar conta Meta no banco.');
        }

        if(!$contaExistenteId){
            $this->metaContaModel->marcarPagamentoMetaPendenteOnboarding($contaId,$clienteId);
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

        if($onboardingType === EmbeddedSignupOnboardingMode::TRADITIONAL || $statusConexao === 'conectado'){
            $this->dispararMetaConectada($clienteId, CanalNotificacao::EMAIL);
        }
        if($onboardingType === EmbeddedSignupOnboardingMode::COEXISTENCE && $statusConexao === 'conectado'){
            $conta = $this->metaContaModel->buscarPorCliente($contaId, $clienteId) ?: [];
            $this->atualizarStatusOperacionalConta($clienteId, $conta, array_merge($dadosWhatsApp, ['status' => 'conectado']));
            try{
                $sync = $this->embeddedSignupFlowService()->iniciarSincronizacaoCoexistence($conta, $this->metaContaModel);
                $this->logMetaEmbeddedSignup([
                    'data'=>date('Y-m-d H:i:s'), 'cliente_id'=>$clienteId, 'conta_id'=>$contaId,
                    'etapa'=>'coexistence_sync_solicitado', 'contact_request_id'=>$sync['contact_request_id'] ?? null,
                    'history_request_id'=>$sync['history_request_id'] ?? null, 'resultado'=>'aceito'
                ]);
            }catch(\Throwable $e){
                $this->logMetaEmbeddedSignup([
                    'data'=>date('Y-m-d H:i:s'), 'cliente_id'=>$clienteId, 'conta_id'=>$contaId,
                    'etapa'=>'coexistence_sync_solicitacao', 'erro'=>$this->sanitizeMetaMessage($e->getMessage()), 'resultado'=>'erro'
                ]);
            }
        }

        return [
            'conta_id' => $contaId,
            'status' => $statusConexao,
            'request_id' => $tentativa['request_id'] ?? null,
            'onboarding_type' => $onboardingType
        ];
    }

    public function confirmarPagamentoMeta()
    {
        \Core\Csrf::exigirPost();
        $usuario=Auth::usuario(); $clienteId=(int)($usuario['CLI_ID']??0); $contaId=(int)($_POST['conta_id']??0);
        $conta=$this->metaContaModel->buscarPorCliente($contaId,$clienteId);
        if(!$conta){ http_response_code(403); Session::flash('error','Conta WhatsApp não encontrada para o seu cliente.'); $this->redirect('configuracao/meta'); }
        $confirmou=$this->metaContaModel->confirmarPagamentoMetaPorCliente($contaId,$clienteId);
        Session::flash($confirmou?'success':'error',$confirmou?'Confirmação registrada. Você informou que a forma de pagamento da Meta já foi configurada.':'Não foi possível registrar a confirmação.');
        $this->redirect('configuracao/meta');
    }

    private function dispararMetaConectada($clienteId, $canal)
    {
        try{
            $cliente = $this->clienteModel->buscar($clienteId);
            if($cliente){
                (new NotificacaoService())->dispararCanal(EventoNotificacao::META_CONECTADA, $canal, $cliente, [
                    'link' => rtrim(BASE_URL, '/') . '/index.php?url=configuracao/meta'
                ]);
            }
        }catch(\Throwable $e){
            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'etapa'=>'notificacao_meta_conectada','erro'=>$this->sanitizeMetaMessage($e->getMessage())]);
        }
    }

    public function finalizarEmbeddedSignup()
    {
        \Core\Csrf::exigirPost();
        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $state = (string) ($_POST['state'] ?? '');
        $code = (string) ($_POST['code'] ?? '');
        $payload = json_decode((string) ($_POST['session_info'] ?? ''), true);

        if($state === '' || $code === ''){
            $this->jsonResponse(['ok'=>false,'message'=>'State ou code ausente no retorno da Meta.'], 422);
        }

        $tentativa = $this->getTentativaEmbedded($state, $clienteId);
        if(!$tentativa){
            $this->jsonResponse(['ok'=>false,'message'=>'Tentativa expirada ou inválida.'], 403);
        }
        if(is_array($payload) && !empty($payload['event'])){
            try{
                $onboardingType = EmbeddedSignupOnboardingMode::fromFinishEvent($payload['event']);
                $this->exigirCoexistenceDisponivel($clienteId, $onboardingType);
            }catch(\InvalidArgumentException $e){
                $this->jsonResponse(['ok'=>false,'message'=>'Evento de conclusão incompatível com o onboarding Meta.'], 422);
            }catch(Exception $e){
                $this->jsonResponse(['ok'=>false,'message'=>$this->sanitizeMetaMessage($e->getMessage())], 403);
            }
            $ids = $this->extrairSessionInfoIds($payload);
            $finish = ['received_at' => time(), 'ids' => $ids];
            if(!$this->embeddedAttemptModel->salvarFinish($state, $clienteId, $finish, $onboardingType)){
                $this->jsonResponse(['ok'=>false,'message'=>'Tentativa já consumida pelo callback.'], 409);
            }
        }

        if(session_status() === PHP_SESSION_ACTIVE){
            session_write_close();
        }

        try{
            $resultado = $this->processarEmbeddedSignupCode($clienteId, $state, $code);
            $coexistence = ($resultado['onboarding_type'] ?? null) === EmbeddedSignupOnboardingMode::COEXISTENCE;
            $connected = $resultado['status'] === 'conectado';
            $this->jsonResponse([
                'ok' => true,
                'connected' => $connected,
                'status' => $resultado['status'],
                'requestId' => $resultado['request_id'],
                'message' => $coexistence
                    ? ($connected ? 'Número conectado com sucesso.' : 'Número vinculado. Aguardando confirmação operacional da Meta.')
                    : 'Número vinculado com sucesso. Falta concluir o registro.'
            ]);
        }catch(Exception $e){
            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'erro'=>$this->sanitizeMetaMessage($e->getMessage())]);
            $this->jsonResponse(['ok'=>false,'message'=>'Não foi possível concluir a conexão com a Meta agora.','detail'=>$this->sanitizeMetaMessage($e->getMessage())], 400);
        }
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

        if(session_status() === PHP_SESSION_ACTIVE){
            session_write_close();
        }

        try{
            $stateCallback = (string) ($_GET['state'] ?? '');
            $resultado = $this->processarEmbeddedSignupCode($clienteId, $stateCallback, (string) $_GET['code'], true);

            $mensagem = ($resultado['onboarding_type'] ?? null) === EmbeddedSignupOnboardingMode::COEXISTENCE
                ? ($resultado['status'] === 'conectado'
                    ? 'Número conectado com sucesso.'
                    : 'Número vinculado. Aguardando confirmação operacional da Meta.')
                : 'Número vinculado com sucesso. Falta concluir o registro no Disparador.net informando o PIN de 6 dígitos.';
            $this->renderMetaCallbackPage(true, $mensagem, $resultado['request_id'] ?? null);
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


    public function registrarNumeroWhatsApp()
    {
        \Core\Csrf::exigirPost();
        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $requestId = $this->requestId();
        $contaId = (int) ($_POST['conta_id'] ?? 0);
        $pin = trim((string) ($_POST['pin'] ?? ''));
        $pinConfirmacao = trim((string) ($_POST['pin_confirmacao'] ?? ''));

        if(!preg_match('/^[0-9]{6}$/', $pin) || $pin !== $pinConfirmacao){
            Session::flash('error', 'Informe e confirme o PIN de 6 dígitos.');
            $this->redirect('configuracao/meta');
        }

        $conta = $this->metaContaModel->buscarPorCliente($contaId, $clienteId);
        if(!$conta || empty($conta['MTA_PhoneNumberId']) || empty($conta['MTA_Token'])){
            Session::flash('error', 'Conta WhatsApp não encontrada ou sem dados suficientes para registro.');
            $this->redirect('configuracao/meta');
        }

        if(($conta['MTA_OnboardingType'] ?? EmbeddedSignupOnboardingMode::TRADITIONAL) === EmbeddedSignupOnboardingMode::COEXISTENCE){
            Session::flash('error', 'Este número não utiliza registro por PIN no Disparador.');
            $this->redirect('configuracao/meta');
        }

        if(($conta['MTA_Status'] ?? '') === 'conectado'){
            Session::flash('success', 'Número já conectado.');
            $this->redirect('configuracao/meta');
        }

        if(!in_array(($conta['MTA_Status'] ?? ''), ['pendente_registro', 'erro_registro', 'requer_acao'], true)){
            Session::flash('warning', 'Esta conta não está pendente de registro.');
            $this->redirect('configuracao/meta');
        }

        try{
            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'conta_id'=>$contaId,'phone_number_id'=>$conta['MTA_PhoneNumberId'],'request_id'=>$requestId,'etapa'=>'register_phone_number','resultado'=>'inicio']);
            $this->registrarPhoneNumberMeta($conta['MTA_PhoneNumberId'], $pin, $conta['MTA_Token']);

            $dadosTelefone = [];
            try{
                $dadosTelefone = $this->sincronizarDadosNumeroMeta($conta);
            }catch(Exception $syncException){
                $this->registrarFalhaSincronizacaoMeta($conta, $requestId, $syncException, 'sync_after_register');
            }

            $statusConexao = $this->atualizarStatusOperacionalConta($clienteId, $conta, array_merge($dadosTelefone, ['status' => 'conectado']));

            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'conta_id'=>$contaId,'phone_number_id'=>$conta['MTA_PhoneNumberId'],'request_id'=>$requestId,'etapa'=>'register_phone_number','status'=>$statusConexao,'resultado'=>'ok']);
            Session::flash('success', empty($dadosTelefone) ? 'Número registrado e conectado com sucesso. Não foi possível sincronizar os dados da Meta agora.' : 'Número registrado e conectado com sucesso.');
        }catch(Exception $e){
            $this->metaContaModel->atualizarStatusOperacionalEmbeddedSignup($contaId, $clienteId, ['status' => 'erro_registro']);
            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'conta_id'=>$contaId,'phone_number_id'=>$conta['MTA_PhoneNumberId'] ?? null,'request_id'=>$requestId,'etapa'=>'register_phone_number','erro'=>$this->sanitizeMetaMessage($e->getMessage()),'resultado'=>'erro']);
            Session::flash('error', $this->mensagemAmigavelErroMeta($e->getMessage()));
        }

        $this->redirect('configuracao/meta');
    }

    public function atualizarStatusMetaAjax()
    {
        \Core\Csrf::exigirPost();
        $usuario = Auth::usuario();

        if(($usuario['nivel'] ?? null) !== 'admin'){
            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>'Acesso negado.'], 403);
        }

        $contaId = (int) ($_POST['conta_id'] ?? 0);
        $conta = $this->metaContaModel->buscarPorIdAdmin($contaId);

        if(!$conta || ($conta['MTA_Ativo'] ?? 'N') !== 'S'){
            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>'Conta Meta não encontrada ou inativa.'], 404);
        }

        if(empty($conta['MTA_PhoneNumberId']) || empty($conta['MTA_Token'])){
            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>'Conta Meta sem token ou Phone Number ID para sincronização.'], 422);
        }

        $requestId = $this->requestId();

        try{
            $dadosTelefone = $this->sincronizarDadosNumeroMeta($conta);
            $this->metaContaModel->atualizarEspelhoMeta((int) $conta['MTA_ID'], (int) $conta['CLI_ID'], $dadosTelefone, null);
            $contaAtualizada = $this->metaContaModel->buscarPorIdAdmin($contaId) ?: [];

            $this->logMetaEmbeddedSignup([
                'data' => date('Y-m-d H:i:s'),
                'cliente_id' => $conta['CLI_ID'],
                'conta_id' => $conta['MTA_ID'],
                'waba_id' => $conta['MTA_WabaId'] ?? null,
                'phone_number_id' => $conta['MTA_PhoneNumberId'],
                'request_id' => $requestId,
                'etapa' => 'admin_refresh_meta_status',
                'resultado' => 'ok'
            ]);

            $this->jsonResponse([
                'ok' => true,
                'status' => 'success',
                'mensagem' => 'Dados da conta atualizados com sucesso.',
                'dados' => [
                    'display_name' => $contaAtualizada['MTA_DisplayName'] ?? ($dadosTelefone['display_name'] ?? null),
                    'numero' => $contaAtualizada['MTA_NumeroTelefone'] ?? ($dadosTelefone['numero'] ?? null),
                    'quality_rating' => $contaAtualizada['MTA_QualityRating'] ?? ($dadosTelefone['quality_rating'] ?? null),
                    'code_verification_status' => $contaAtualizada['MTA_CodeVerificationStatus'] ?? ($dadosTelefone['code_verification_status'] ?? null),
                    'name_status' => $contaAtualizada['MTA_NameStatus'] ?? ($dadosTelefone['name_status'] ?? null),
                    'operational_status' => $contaAtualizada['MTA_OperationalStatus'] ?? ($dadosTelefone['operational_status'] ?? null),
                    'ultima_verificacao' => $contaAtualizada['MTA_UltimaVerificacao'] ?? date('Y-m-d H:i:s'),
                    'messaging_limit' => $contaAtualizada['MTA_MessagingLimit'] ?? ($dadosTelefone['messaging_limit'] ?? null),
                    'messaging_limit_label' => \Services\MetaService::formatarLimiteConversasMeta($contaAtualizada['MTA_MessagingLimit'] ?? ($dadosTelefone['messaging_limit'] ?? null))
                ]
            ]);
        }catch(Exception $e){
            $this->logMetaEmbeddedSignup([
                'data' => date('Y-m-d H:i:s'),
                'cliente_id' => $conta['CLI_ID'] ?? null,
                'conta_id' => $conta['MTA_ID'] ?? null,
                'waba_id' => $conta['MTA_WabaId'] ?? null,
                'phone_number_id' => $conta['MTA_PhoneNumberId'] ?? null,
                'request_id' => $requestId,
                'etapa' => 'admin_refresh_meta_status',
                'erro' => $this->sanitizeMetaMessage($e->getMessage()),
                'resultado' => 'erro'
            ]);

            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>$this->mensagemAmigavelErroMeta($e->getMessage())], 400);
        }
    }

    public function repetirSyncCoexistenceAjax()
    {
        \Core\Csrf::exigirPost();
        $usuario = Auth::usuario();
        if(($usuario['nivel'] ?? null) !== 'admin'){
            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>'Acesso negado.'], 403);
        }

        $contaId = (int) ($_POST['conta_id'] ?? 0);
        $tipo = trim((string) ($_POST['tipo'] ?? ''));
        $conta = $this->metaContaModel->buscarPorIdAdmin($contaId);
        if(!$conta || ($conta['MTA_Ativo'] ?? 'N') !== 'S'){
            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>'Conta Meta não encontrada ou inativa.'], 404);
        }
        if(empty($conta['MTA_PhoneNumberId']) || empty($conta['MTA_Token'])){
            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>'Conta Meta sem token ou Phone Number ID para sincronização.'], 422);
        }

        try{
            $resultado = $this->embeddedSignupFlowService()->repetirSincronizacaoCoexistence(
                $conta,
                $this->metaContaModel,
                $tipo,
                function($requestIdAnterior) use ($conta, $tipo, $usuario){
                    $requestIdAnterior = preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $requestIdAnterior);
                    $this->logMetaEmbeddedSignup([
                        'data'=>date('Y-m-d H:i:s'),
                        'cliente_id'=>$conta['CLI_ID'] ?? null,
                        'conta_id'=>$conta['MTA_ID'] ?? null,
                        'usuario_id'=>$usuario['id'] ?? null,
                        'etapa'=>'admin_coexistence_sync_retry_reservado',
                        'sync_type'=>$tipo,
                        'previous_request_id'=>mb_substr($requestIdAnterior, 0, 100),
                        'resultado'=>'reservado'
                    ]);
                }
            );
            if(empty($resultado['iniciado'])){
                $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>'Sync não elegível para retry ou já reservado por outra operação.'], 409);
            }
            $this->logMetaEmbeddedSignup([
                'data'=>date('Y-m-d H:i:s'), 'cliente_id'=>$conta['CLI_ID'] ?? null,
                'conta_id'=>$conta['MTA_ID'] ?? null, 'usuario_id'=>$usuario['id'] ?? null,
                'etapa'=>'admin_coexistence_sync_retry_solicitado', 'sync_type'=>$tipo,
                'request_id'=>$resultado['request_id'] ?? null, 'resultado'=>'ok'
            ]);
            $this->jsonResponse(['ok'=>true,'status'=>'success','mensagem'=>'Retry Coexistence solicitado com sucesso.','request_id'=>$resultado['request_id']]);
        }catch(\InvalidArgumentException $e){
            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>$e->getMessage()], 422);
        }catch(\Throwable $e){
            $this->logMetaEmbeddedSignup([
                'data'=>date('Y-m-d H:i:s'), 'cliente_id'=>$conta['CLI_ID'] ?? null,
                'conta_id'=>$conta['MTA_ID'] ?? null, 'usuario_id'=>$usuario['id'] ?? null,
                'etapa'=>'admin_coexistence_sync_retry', 'sync_type'=>$tipo,
                'erro'=>$this->sanitizeMetaMessage($e->getMessage()), 'resultado'=>'erro'
            ]);
            $this->jsonResponse(['ok'=>false,'status'=>'error','mensagem'=>'Não foi possível repetir a sincronização Coexistence.'], 400);
        }
    }

    public function atualizarStatusNumeroWhatsApp()
    {
        \Core\Csrf::exigirPost();
        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $requestId = $this->requestId();
        $contaId = (int) ($_POST['conta_id'] ?? 0);
        $conta = $this->metaContaModel->buscarPorCliente($contaId, $clienteId);

        if(!$conta || empty($conta['MTA_PhoneNumberId']) || empty($conta['MTA_Token'])){
            Session::flash('error', 'Conta WhatsApp não encontrada ou sem dados suficientes para atualização.');
            $this->redirect('configuracao/meta');
        }

        try{
            $dadosTelefone = $this->buscarDadosPhoneNumber($conta['MTA_PhoneNumberId'], $conta['MTA_Token']);
            $statusConexao = $this->atualizarStatusOperacionalConta($clienteId, $conta, $dadosTelefone);
            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'conta_id'=>$contaId,'phone_number_id'=>$conta['MTA_PhoneNumberId'],'request_id'=>$requestId,'etapa'=>'refresh_phone_number_status','status'=>$statusConexao,'resultado'=>'ok']);
            Session::flash($statusConexao === 'conectado' ? 'success' : 'info', $statusConexao === 'conectado' ? 'Número conectado com sucesso.' : 'Status atualizado. O número ainda requer ação na Meta.');
        }catch(Exception $e){
            $this->logMetaEmbeddedSignup(['data'=>date('Y-m-d H:i:s'),'cliente_id'=>$clienteId,'conta_id'=>$contaId,'phone_number_id'=>$conta['MTA_PhoneNumberId'] ?? null,'request_id'=>$requestId,'etapa'=>'refresh_phone_number_status','erro'=>$this->sanitizeMetaMessage($e->getMessage()),'resultado'=>'erro']);
            Session::flash('error', 'Não foi possível atualizar o status do número agora.');
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
            $this->avaliarPermissaoConexao(
                $usuario['CLI_ID']
            );

        $this->view(
            'configuracao/meta',
            [
                'titulo' => 'Números WhatsApp',
                'contas' => $contas,
                'limiteNumeros' => $limiteNumeros,
                'coexistenceDisponivel' => $this->coexistenceEligibility()->availableForClient((int) $usuario['CLI_ID'])
            ]
        );
    }
}
