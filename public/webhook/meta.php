<?php

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
$input =
    file_get_contents(
        'php://input'
    );

file_put_contents(
    __DIR__ . '/meta.log',
    date('Y-m-d H:i:s')
    . "\n"
    . $input
    . "\n\n",
    FILE_APPEND
);

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

            foreach($value['statuses'] as $status){

                $messageId =
                    $status['id']
                    ?? null;

                $statusMsg =
                    $status['status']
                    ?? null;

                if(!$messageId || !$statusMsg){
                    continue;
                }

                $statusInterno = mapearStatusMeta($statusMsg);
                $erroMeta = extrairErroStatusMeta($status);
                $retornoStatus = json_encode($status, JSON_UNESCAPED_UNICODE);

                $sql = $db->prepare("
                    UPDATE conversa_mensagens
                    SET
                        MSG_Status = ?,
                        MSG_Retorno = ?
                    WHERE MSG_MetaMessageId = ?
                ");

                $sql->execute([
                    $statusInterno,
                    $retornoStatus,
                    $messageId
                ]);

                $db->prepare("
                    UPDATE disparos
                    SET
                        DSP_Status = ?,
                        DSP_Retorno = ?
                    WHERE DSP_MessageId = ?
                ")->execute([
                    $statusInterno,
                    $retornoStatus,
                    $messageId
                ]);

                $db->prepare("
                    UPDATE fila_envio
                    SET
                        FIL_Status = ?,
                        FIL_Erro = CASE WHEN ? IS NOT NULL THEN ? ELSE FIL_Erro END,
                        FIL_Retorno = ?
                    WHERE FIL_MessageId = ?
                ")->execute([
                    $statusInterno,
                    $erroMeta,
                    $erroMeta,
                    $retornoStatus,
                    $messageId
                ]);

            }

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

function mapearStatusMeta($status)
{
    $mapa = [
        'sent' => 'enviada',
        'delivered' => 'entregue',
        'read' => 'lida',
        'failed' => 'falhou'
    ];

    return $mapa[$status] ?? $status;
}



function extrairErroStatusMeta($status)
{
    $erro = $status['errors'][0] ?? null;

    if(!$erro){
        return null;
    }

    $partes = [];

    foreach(['title', 'message', 'details', 'error_data'] as $campo){
        if(empty($erro[$campo])){
            continue;
        }

        $partes[] = is_array($erro[$campo])
            ? json_encode($erro[$campo], JSON_UNESCAPED_UNICODE)
            : $erro[$campo];
    }

    if(!empty($erro['code'])){
        $partes[] = 'Código: ' . $erro['code'];
    }

    return trim(implode(' | ', array_filter($partes))) ?: 'Falha confirmada pela Meta';
}
