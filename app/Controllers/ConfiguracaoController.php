<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\MetaConta;
use Models\Cliente;
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

    private function graphRequest($endpoint, array $params = [], $accessToken = null)
    {
        $version = trim((string) META_GRAPH_VERSION);
        $version = $version !== '' ? ltrim($version, '/') : 'v20.0';
        $url = 'https://graph.facebook.com/' . $version . '/' . ltrim($endpoint, '/');

        if($accessToken !== null){
            $params['access_token'] = $accessToken;
        }

        if(!empty($params)){
            $url .= '?' . http_build_query($params);
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

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
            throw new Exception('Erro da Meta: ' . json_encode($json['error'] ?? $json, JSON_UNESCAPED_UNICODE));
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

    private function extrairWabaIdsDoDebugToken($accessToken)
    {
        $appToken = META_APP_ID . '|' . META_APP_SECRET;
        $debug = $this->graphRequest('debug_token', [
            'input_token' => $accessToken
        ], $appToken);

        $ids = [];
        $businessId = $debug['data']['profile_id'] ?? null;

        foreach(($debug['data']['granular_scopes'] ?? []) as $scope){
            if(($scope['scope'] ?? '') !== 'whatsapp_business_management'){
                continue;
            }

            foreach(($scope['target_ids'] ?? []) as $targetId){
                $ids[] = (string) $targetId;
            }
        }

        return [array_values(array_unique($ids)), $businessId];
    }

    private function buscarDadosWhatsApp($accessToken)
    {
        [$wabaIds, $businessId] = $this->extrairWabaIdsDoDebugToken($accessToken);

        foreach($wabaIds as $wabaId){
            $waba = $this->graphRequest($wabaId, [
                'fields' => 'id,name,phone_numbers{id,display_phone_number,verified_name}'
            ], $accessToken);

            $telefone = $waba['phone_numbers']['data'][0] ?? null;

            if(!$telefone || empty($telefone['id'])){
                continue;
            }

            return [
                'business_id' => $businessId,
                'waba_id' => $waba['id'] ?? $wabaId,
                'waba_name' => $waba['name'] ?? null,
                'phone_number_id' => $telefone['id'],
                'numero' => $telefone['display_phone_number'] ?? '',
                'display_name' => $telefone['verified_name'] ?? ($waba['name'] ?? null)
            ];
        }

        throw new Exception('Não foi possível identificar WABA/Phone Number no token retornado pela Meta.');
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

        if(!\Core\Csrf::validar($_GET['state'] ?? '')){
            $this->logMetaEmbeddedSignup([
                'data' => date('Y-m-d H:i:s'),
                'cliente_id' => $clienteId,
                'erro' => 'state_csrf_invalido'
            ]);

            Session::flash(
                'error',
                'Não foi possível validar a segurança do retorno da Meta. Reabra a tela e tente conectar novamente.'
            );

            $this->redirect('configuracao/meta');
        }

        try{
            $accessToken = $this->trocarCodePorToken((string) $_GET['code']);
            $dadosWhatsApp = $this->buscarDadosWhatsApp($accessToken);

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
                'display_name' => $dadosWhatsApp['display_name']
            ]);

            if(!$contaId){
                throw new Exception('Falha ao salvar conta Meta no banco.');
            }

            $this->clienteModel->iniciarTrialSePendente($clienteId);

            if(isset($_SESSION['usuario'])){
                $_SESSION['usuario']['CLI_DataLiberacao'] = $_SESSION['usuario']['CLI_DataLiberacao'] ?? date('Y-m-d H:i:s');
            }

            $this->logMetaEmbeddedSignup([
                'data' => date('Y-m-d H:i:s'),
                'cliente_id' => $clienteId,
                'conta_id' => $contaId,
                'waba_id' => $dadosWhatsApp['waba_id'],
                'phone_number_id' => $dadosWhatsApp['phone_number_id'],
                'status' => 'conectado'
            ]);

            Session::flash(
                'success',
                'WhatsApp conectado com sucesso. A conta já está disponível para sincronizar templates e enviar mensagens.'
            );
        }catch(Exception $e){
            $this->logMetaEmbeddedSignup([
                'data' => date('Y-m-d H:i:s'),
                'cliente_id' => $clienteId,
                'erro' => $e->getMessage()
            ]);

            Session::flash(
                'error',
                'Não foi possível concluir a conexão com a Meta agora. Tente novamente e, se persistir, acione o suporte.'
            );
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
