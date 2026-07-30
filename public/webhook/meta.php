<?php

$input = file_get_contents('php://input');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

spl_autoload_register(function($class){

    $class = str_replace('\\', '/', $class);

    $file = __DIR__ . '/../../app/' . $class . '.php';

    if(file_exists($file)){
        require_once $file;
    }

});

use Core\Database;
use Models\Conversa;
use Services\MensagemStatusService;
use Services\MetaStatusWebhookService;

$db = Database::getInstance();





/*
|--------------------------------------------------------------------------
| Validação do webhook pela Meta
|--------------------------------------------------------------------------
*/
if($_SERVER['REQUEST_METHOD'] == 'GET'){

    $mode =
        $_GET['hub_mode']
        ?? $_GET['hub.mode']
        ?? null;

    $token =
        $_GET['hub_verify_token']
        ?? $_GET['hub.verify_token']
        ?? null;

    $challenge =
        $_GET['hub_challenge']
        ?? $_GET['hub.challenge']
        ?? null;

    if($mode == 'subscribe' && $token){

        $sql = $db->prepare("
            SELECT MTA_ID
            FROM meta_contas
            WHERE MTA_WebhookVerifyToken = ?
            AND MTA_Ativo = 'S'
            LIMIT 1
        ");

        $sql->execute([
            $token
        ]);

        $conta =
            $sql->fetch(PDO::FETCH_ASSOC);

        if($conta){

            http_response_code(200);
            echo $challenge;
            exit;

        }
    }

    http_response_code(403);
    echo 'Token inválido';
    exit;
}





/*
|--------------------------------------------------------------------------
| Recebimento dos eventos
|--------------------------------------------------------------------------
*/

if(!validarAssinaturaMeta($input)){
    http_response_code(403);
    registrarLogWebhookMeta('assinatura_invalida');
    echo 'Assinatura inválida';
    exit;
}

registrarLogWebhookMeta('payload_recebido', [
    'bytes' => strlen($input)
]);

$payload =
    json_decode(
        $input,
        true
    );

if(empty($payload)){

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}





$conversaModel =
    new Conversa();

$statusWebhookService = new MetaStatusWebhookService(
    $conversaModel,
    function($messageId, $status, array $erro) use ($db){
        atualizarRegistrosSecundariosStatus($db, $messageId, $status, $erro);
    }
);

$entries =
    $payload['entry']
    ?? [];

foreach($entries as $entry){

    $changes =
        $entry['changes']
        ?? [];

    foreach($changes as $change){

        $value =
            $change['value']
            ?? [];

        $phoneNumberId =
            $value['metadata']['phone_number_id']
            ?? null;

        if(!$phoneNumberId){
            continue;
        }

        $sql = $db->prepare("
            SELECT *
            FROM meta_contas
            WHERE MTA_PhoneNumberId = ?
            AND MTA_Ativo = 'S'
            LIMIT 1
        ");

        $sql->execute([
            $phoneNumberId
        ]);

        $metaConta =
            $sql->fetch(PDO::FETCH_ASSOC);

        if(!$metaConta){
            continue;
        }





        /*
        |--------------------------------------------------------------------------
        | Mensagens recebidas
        |--------------------------------------------------------------------------
        */
        if(!empty($value['messages'])){

            foreach($value['messages'] as $msg){

                $numero =
                    $msg['from']
                    ?? null;

                if(!$numero){
                    continue;
                }

                $tipo =
                    $msg['type']
                    ?? 'text';

                $texto = '';

                if($tipo == 'text'){

                    $texto =
                        $msg['text']['body']
                        ?? '';

                }elseif($tipo == 'button'){

                    $texto =
                        $msg['button']['text']
                        ?? '[Botão]';

                }elseif($tipo == 'interactive'){

                    $texto =
                        $msg['interactive']['button_reply']['title']
                        ??
                        $msg['interactive']['list_reply']['title']
                        ??
                        '[Interativo]';

                }else{

                    $texto =
                        '[' . strtoupper($tipo) . ']';

                }

                $nomeContato =
                    $value['contacts'][0]['profile']['name']
                    ?? null;

                $messageId =
                    $msg['id']
                    ?? null;

                $dataMensagem =
                    !empty($msg['timestamp'])
                    ? date('Y-m-d H:i:s', $msg['timestamp'])
                    : date('Y-m-d H:i:s');

                $conversaId =
                    $conversaModel->buscarOuCriar(
                        $metaConta['CLI_ID'],
                        $metaConta['MTA_ID'],
                        $numero,
                        $nomeContato
                    );

                $conversaModel->salvarMensagem([

                    'conversa_id' =>
                        $conversaId,

                    'direcao' =>
                        'recebida',

                    'tipo' =>
                        $tipo,

                    'texto' =>
                        $texto,

                    'message_id' =>
                        $messageId,

                    'status' =>
                        'recebida',

                    'retorno' =>
                        $msg,

                    'data_mensagem' =>
                        $dataMensagem

                ]);


                processarAutoResposta(
                    $db,
                    $conversaModel,
                    $metaConta,
                    $conversaId,
                    $numero
                );

            }

        }





        /*
        |--------------------------------------------------------------------------
        | Status das mensagens enviadas
        |--------------------------------------------------------------------------
        */
        if(!empty($value['statuses'])){
            $statusWebhookService->processarLote($value['statuses']);

        }

    }

}

http_response_code(200);

echo 'EVENT_RECEIVED';




function processarAutoResposta($db, $conversaModel, $metaConta, $conversaId, $numero)
{
    try{

        if(
            ($metaConta['MTA_AutoRespostaAtiva'] ?? 'N') != 'S'
            ||
            trim($metaConta['MTA_AutoRespostaTexto'] ?? '') == ''
        ){
            return;
        }

        if(!colunaExiste($db, 'conversas', 'CVS_DataUltimaAutoResposta')){
            registrarLogAutoResposta(
                $metaConta,
                $conversaId,
                $numero,
                'ignorada_coluna_cvs_data_ultima_auto_resposta_ausente'
            );
            return;
        }

        $sql = $db->prepare("
            SELECT CVS_DataUltimaAutoResposta
            FROM conversas
            WHERE CVS_ID = ?
            LIMIT 1
        ");

        $sql->execute([
            $conversaId
        ]);

        $ultima = $sql->fetchColumn();
        $intervalo = max(5, (int) ($metaConta['MTA_AutoRespostaIntervaloMinutos'] ?? 1440));

        if($ultima && strtotime($ultima) > strtotime('-' . $intervalo . ' minutes')){
            registrarLogAutoResposta(
                $metaConta,
                $conversaId,
                $numero,
                'ignorada_por_intervalo'
            );
            return;
        }

        $response = enviarAutoRespostaTexto(
            $metaConta,
            $numero,
            trim($metaConta['MTA_AutoRespostaTexto'])
        );

        $messageId = $response['response']['messages'][0]['id'] ?? null;

        if($response['http_code'] < 200 || $response['http_code'] >= 300 || !$messageId){
            registrarLogAutoResposta(
                $metaConta,
                $conversaId,
                $numero,
                'erro_envio',
                json_encode($response['response'], JSON_UNESCAPED_UNICODE)
            );
            return;
        }

        $conversaModel->salvarMensagem([
            'conversa_id' => $conversaId,
            'direcao' => 'enviada',
            'tipo' => 'text',
            'texto' => trim($metaConta['MTA_AutoRespostaTexto']),
            'message_id' => $messageId,
            'status' => 'aguardando_confirmacao',
            'retorno' => $response,
            'data_mensagem' => date('Y-m-d H:i:s')
        ]);

        $db->prepare("
            UPDATE conversas
            SET CVS_DataUltimaAutoResposta = NOW()
            WHERE CVS_ID = ?
        ")->execute([
            $conversaId
        ]);

        registrarLogAutoResposta(
            $metaConta,
            $conversaId,
            $numero,
            'enviada'
        );

    }catch(Exception $e){

        registrarLogAutoResposta(
            $metaConta,
            $conversaId,
            $numero,
            'erro',
            $e->getMessage()
        );
    }
}

function enviarAutoRespostaTexto($metaConta, $numero, $texto)
{
    $url = rtrim($metaConta['MTA_UrlBase'], '/')
        . '/'
        . $metaConta['MTA_PhoneNumberId']
        . '/messages';

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $numero,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $texto
        ]
    ];

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $metaConta['MTA_Token']
        ]
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'curl_error' => $curlError
    ];
}

function validarAssinaturaMeta($input)
{
    $appSecret = defined('META_APP_SECRET') ? (string) META_APP_SECRET : '';

    if($appSecret === ''){
        return false;
    }

    $assinatura = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

    if(strpos($assinatura, 'sha256=') !== 0){
        return false;
    }

    $esperada = 'sha256=' . hash_hmac('sha256', $input, $appSecret);

    return hash_equals($esperada, $assinatura);
}

function registrarLogWebhookMeta($acao, $dados = [])
{
    $diretorioLog = __DIR__ . '/../../storage/logs';

    if(!is_dir($diretorioLog)){
        mkdir($diretorioLog, 0775, true);
    }

    $linha = [
        'data' => date('Y-m-d H:i:s'),
        'acao' => $acao,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'dados' => $dados
    ];

    file_put_contents(
        $diretorioLog . '/meta-webhook.log',
        json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

function colunaExiste($db, $tabela, $coluna)
{
    $sql = $db->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");

    $sql->execute([
        $tabela,
        $coluna
    ]);

    return (int) $sql->fetchColumn() > 0;
}

function registrarLogAutoResposta($metaConta, $conversaId, $numero, $acao, $erro = null)
{
    $linha = sprintf(
        "[%s] MTA_ID=%s CLI_ID=%s conversa_id=%s numero=%s acao=%s%s\n",
        date('Y-m-d H:i:s'),
        $metaConta['MTA_ID'] ?? '',
        $metaConta['CLI_ID'] ?? '',
        $conversaId,
        $numero,
        $acao,
        $erro ? ' erro=' . $erro : ''
    );

    $diretorioLog = __DIR__ . '/../../storage/logs';

    if(!is_dir($diretorioLog)){
        mkdir($diretorioLog, 0775, true);
    }

    file_put_contents(
        $diretorioLog . '/meta_autoresposta.log',
        $linha,
        FILE_APPEND
    );
}

function atualizarRegistrosSecundariosStatus($db, $messageId, $status, array $erro)
{
    $permitidos = MensagemStatusService::statusAtuaisPermitidos($status);
    if(!$permitidos) return;
    $placeholders = implode(',', array_fill(0, count($permitidos), '?'));
    $retornoSeguro = json_encode(['status'=>$status, 'error_code'=>$erro['codigo'] ?? null], JSON_UNESCAPED_UNICODE);
    foreach([
        ['disparos','DSP_Status','DSP_Retorno','DSP_MessageId',null,null],
        ['fila_envio','FIL_Status','FIL_Retorno','FIL_MessageId','FIL_Erro',null],
        ['disparo_manual_itens','DMI_Status','DMI_Retorno','DMI_MessageId','DMI_Erro','DMI_DataAtualizacao'],
    ] as $alvo){
        [$tabela,$campoStatus,$campoRetorno,$campoId,$campoErro,$campoAtualizacao] = $alvo;
        $setErro = $campoErro ? ", {$campoErro}=CASE WHEN ?='failed' THEN ? ELSE {$campoErro} END" : '';
        $setAtualizacao = $campoAtualizacao ? ", {$campoAtualizacao}=NOW()" : '';
        $sql = $db->prepare("UPDATE {$tabela} SET {$campoStatus}=?, {$campoRetorno}=?{$setErro}{$setAtualizacao} WHERE {$campoId}=? AND ({$campoStatus} IS NULL OR {$campoStatus} IN ({$placeholders}))");
        $params = [$status, $retornoSeguro];
        if($campoErro){ $params[]=$status; $params[]=MensagemStatusService::sanitizarErro($erro['mensagem'] ?? null); }
        $params[]=$messageId; $params=array_merge($params,$permitidos); $sql->execute($params);
    }
}
