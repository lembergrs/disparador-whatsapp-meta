<?php

require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';


spl_autoload_register(function($class){

    $class = str_replace('\\', '/', $class);

    $file = __DIR__ . '/app/' . $class . '.php';

    if(file_exists($file)){
        require_once $file;
    }

});

use Core\Database;
use Services\MetaService;
use Services\ControlePlanoService;
use Models\Conversa;
use Models\ConsumoMensal;
use Models\Disparo;

$modoTeste = false; // troque para false para envio real
$limitePorExecucao = 50;
$limiteDisparoManualPorExecucao = 20;

$db = Database::getInstance();

processarDisparosManuais($db, $limiteDisparoManualPorExecucao, $modoTeste);

$campanhas = $db->query("
    SELECT *
    FROM campanhas
    WHERE CAM_Status = 'agendada'
    AND CAM_DataAgendamento <= NOW()
")->fetchAll(PDO::FETCH_ASSOC);

foreach($campanhas as $campanha){

    echo "Campanha {$campanha['CAM_ID']} iniciada.\n";

    $db->prepare("
        UPDATE campanhas
        SET CAM_Status = 'processando'
        WHERE CAM_ID = ?
    ")->execute([
        $campanha['CAM_ID']
    ]);

}





$campanhas = $db->query("
    SELECT *
    FROM campanhas
    WHERE CAM_Status = 'processando'
")->fetchAll(PDO::FETCH_ASSOC);

foreach($campanhas as $campanha){

    echo "Processando campanha {$campanha['CAM_ID']}...\n";

    $stmt = $db->prepare("
        SELECT *
        FROM templates_meta
        WHERE TMP_ID = ?
    ");

    $stmt->execute([
        $campanha['TMP_ID']
    ]);

    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$template){

        $db->prepare("
            UPDATE campanhas
            SET CAM_Status = 'cancelada'
            WHERE CAM_ID = ?
        ")->execute([
            $campanha['CAM_ID']
        ]);

        continue;
    }

    $stmt = $db->prepare("
        SELECT *
        FROM campanha_variaveis
        WHERE CAM_ID = ?
        ORDER BY CAST(CPV_Variavel AS UNSIGNED) ASC
    ");

    $stmt->execute([
        $campanha['CAM_ID']
    ]);

    $variaveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT
            f.*,
            c.CON_Nome,
            c.CON_Telefone,
            c.CON_DadosJson
        FROM fila_envio f
        INNER JOIN contatos c
            ON c.CON_ID = f.CON_ID
        WHERE f.CAM_ID = ?
        AND f.FIL_Status = 'pendente'
        ORDER BY f.FIL_ID ASC
        LIMIT {$limitePorExecucao}
    ");

    $stmt->execute([
        $campanha['CAM_ID']
    ]);

    $fila = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($fila)){

        finalizarSeConcluida($db, $campanha['CAM_ID']);

        continue;
    }

    $meta = null;

    if(!$modoTeste){
        $meta = new MetaService($template['MTA_ID']);
    }

    foreach($fila as $item){

        $db->prepare("
            UPDATE fila_envio
            SET
                FIL_Status = 'processando',
                FIL_Tentativas = FIL_Tentativas + 1
            WHERE FIL_ID = ?
        ")->execute([
            $item['FIL_ID']
        ]);

        $dadosContato = json_decode(
            $item['CON_DadosJson'],
            true
        );

        if(!is_array($dadosContato)){
            $dadosContato = [];
        }

        $parametros = [];

        foreach($variaveis as $var){

            $campo = $var['CPV_Campo'];

            $parametros[$var['CPV_Variavel']] =
                $dadosContato[$campo]
                ?? '';

        }

        try{

            if($modoTeste){

                echo "SIMULAÇÃO\n";
                echo "Campanha: {$campanha['CAM_ID']}\n";
                echo "Contato: {$item['CON_Nome']}\n";
                echo "Telefone: {$item['CON_Telefone']}\n";
                echo "Template: {$template['TMP_Nome']}\n";
                echo "Parâmetros:\n";
                foreach($parametros as $chave => $valor){
                    echo $chave . ': ' . $valor . "\n";
                }
                echo "-------------------------\n";

                $retorno = [
                    'messages' => [
                        [
                            'id' => 'SIMULACAO'
                        ]
                    ]
                ];

            }else{

                $retorno =
                    $meta->enviarTemplate(
                        $item['CON_Telefone'],
                        $template,
                        $parametros
                    );

            }

            if(isset($retorno['messages'][0]['id'])){

                $db->prepare("
                    UPDATE fila_envio
                    SET
                        FIL_Status = 'aguardando_confirmacao',
                        FIL_DataEnvio = NOW(),
                        FIL_Erro = NULL,
                        FIL_MessageId = ?,
                        FIL_Retorno = ?
                    WHERE FIL_ID = ?
                ")->execute([
                    $retorno['messages'][0]['id'],
                    json_encode($retorno, JSON_UNESCAPED_UNICODE),
                    $item['FIL_ID']
                ]);

                $db->prepare("
                    UPDATE campanhas
                    SET CAM_TotalEnviados = CAM_TotalEnviados + 1
                    WHERE CAM_ID = ?
                ")->execute([
                    $campanha['CAM_ID']
                ]);

                $consumo =
                    new ConsumoMensal();

                $consumo->registrarMensagem(
                    $campanha['CLI_ID']
                );

                $controlePlano =
                    new ControlePlanoService();

                $controlePlano->registrarUso(
                    $campanha['CLI_ID']
                );

                $conversaModel =
                    new Conversa();

                $conversaId =
                    $conversaModel->buscarOuCriar(
                        $campanha['CLI_ID'],
                        $template['MTA_ID'],
                        $item['CON_Telefone'],
                        $item['CON_Nome']
                    );

                $conversaModel->salvarMensagem([

                    'conversa_id' =>
                        $conversaId,

                    'direcao' =>
                        'enviada',

                    'tipo' =>
                        'template',

                    'texto' =>
                        $template['TMP_Nome'],

                    'message_id' =>
                        $retorno['messages'][0]['id'],

                    'status' =>
                        'aguardando_confirmacao',

                    'retorno' =>
                        $retorno,

                    'data_mensagem' =>
                        date('Y-m-d H:i:s')

                ]);

            }else{

                registrarErro(
                    $db,
                    $campanha['CAM_ID'],
                    $item['FIL_ID'],
                    extrairErroMetaWorker($retorno),
                    $retorno
                );

            }

        }catch(Exception $e){

            registrarErro(
                $db,
                $campanha['CAM_ID'],
                $item['FIL_ID'],
                $e->getMessage()
            );

        }

        aplicarLimiteEnvio($retorno ?? null);
    }

    finalizarSeConcluida($db, $campanha['CAM_ID']);

}





function registrarErro($db, $campanhaId, $filaId, $erro, $retorno = null)
{
    $db->prepare("
        UPDATE fila_envio
        SET
            FIL_Status = 'erro',
            FIL_Erro = ?,
            FIL_Retorno = ?
        WHERE FIL_ID = ?
    ")->execute([
        $erro,
        json_encode($retorno, JSON_UNESCAPED_UNICODE),
        $filaId
    ]);

    $db->prepare("
        UPDATE campanhas
        SET CAM_TotalErros = CAM_TotalErros + 1
        WHERE CAM_ID = ?
    ")->execute([
        $campanhaId
    ]);
}





function finalizarSeConcluida($db, $campanhaId)
{
    $stmt = $db->prepare("
        SELECT COUNT(*) total
        FROM fila_envio
        WHERE CAM_ID = ?
        AND FIL_Status IN ('pendente','processando')
    ");

    $stmt->execute([
        $campanhaId
    ]);

    $total =
        $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    if($total == 0){

        $db->prepare("
            UPDATE campanhas
            SET CAM_Status = 'finalizada'
            WHERE CAM_ID = ?
        ")->execute([
            $campanhaId
        ]);

        echo "Campanha {$campanhaId} finalizada.\n";

    }else{

        echo "Campanha {$campanhaId} ainda possui {$total} pendentes.\n";

    }
}



function aplicarLimiteEnvio($retorno = null)
{
    if(ehRateLimitMeta($retorno)){
        echo "Limite de envio da Meta atingido. Pausando lote temporariamente.\n";
        sleep(WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS);
        return;
    }

    $enviosPorSegundo = max(1, (int) WHATSAPP_ENVIOS_POR_SEGUNDO);
    usleep((int) round(1000000 / $enviosPorSegundo));
}



function ehRateLimitMeta($retorno)
{
    if(!is_array($retorno)){
        return false;
    }

    $codigoHttp = (int) ($retorno['http_code'] ?? 0);
    $codigoErro = (int) ($retorno['error']['code'] ?? 0);
    $mensagem = strtolower((string) ($retorno['error']['message'] ?? ''));

    return $codigoHttp == 429
        || in_array($codigoErro, [4, 17, 32, 613], true)
        || strpos($mensagem, 'rate limit') !== false
        || strpos($mensagem, 'too many') !== false;
}




function extrairErroMetaWorker($retorno)
{
    if(ehRateLimitMeta($retorno)){
        return 'Limite de envio da Meta atingido. O lote foi pausado temporariamente e deve ser retomado com velocidade reduzida.';
    }

    if(is_array($retorno) && !empty($retorno['error']['message'])){
        return $retorno['error']['message'];
    }

    return is_array($retorno)
        ? json_encode($retorno, JSON_UNESCAPED_UNICODE)
        : 'Erro ao enviar mensagem';
}


function processarDisparosManuais($db, $limite, $modoTeste = false)
{
    $db->query("
        UPDATE disparo_manual_lotes
        SET DML_Status = 'processando', DML_DataAtualizacao = NOW()
        WHERE DML_Status = 'pendente'
    ");

    $stmt = $db->prepare("
        SELECT
            i.*,
            l.MTA_ID,
            l.TMP_ID,
            t.*
        FROM disparo_manual_itens i
        INNER JOIN disparo_manual_lotes l ON l.DML_ID = i.DML_ID
        INNER JOIN templates_meta t ON t.TMP_ID = l.TMP_ID
        WHERE i.DMI_Status = 'pendente'
        AND l.DML_Status IN ('pendente','processando')
        ORDER BY i.DMI_ID ASC
        LIMIT {$limite}
    ");

    $stmt->execute();
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($itens)){
        finalizarLotesManuaisConcluidos($db);
        return;
    }

    $metaCache = [];
    $disparoModel = new Disparo();
    $conversaModel = new Conversa();
    $consumo = new ConsumoMensal();
    $controlePlano = new ControlePlanoService();

    foreach($itens as $item){
        $db->prepare("
            UPDATE disparo_manual_itens
            SET DMI_Status = 'processando', DMI_DataAtualizacao = NOW()
            WHERE DMI_ID = ?
            AND DMI_Status = 'pendente'
        ")->execute([$item['DMI_ID']]);

        $variaveis = json_decode($item['DMI_VariaveisJson'] ?? '[]', true);

        if(!is_array($variaveis)){
            $variaveis = [];
        }

        try{
            if($modoTeste){
                $retorno = [
                    'messages' => [
                        ['id' => 'SIMULACAO_MANUAL_' . $item['DMI_ID']]
                    ]
                ];
            }else{
                $metaKey = $item['MTA_ID'] . ':' . $item['CLI_ID'];

                if(empty($metaCache[$metaKey])){
                    $metaCache[$metaKey] = new MetaService($item['MTA_ID'], $item['CLI_ID']);
                }

                $retorno = $metaCache[$metaKey]->enviarTemplate(
                    $item['DMI_Numero'],
                    $item,
                    $variaveis
                );
            }

            if(isset($retorno['messages'][0]['id'])){
                $messageId = $retorno['messages'][0]['id'];

                $db->prepare("
                    UPDATE disparo_manual_itens
                    SET
                        DMI_Status = 'aguardando_confirmacao',
                        DMI_MessageId = ?,
                        DMI_Retorno = ?,
                        DMI_Erro = NULL,
                        DMI_DataEnvio = NOW(),
                        DMI_DataAtualizacao = NOW()
                    WHERE DMI_ID = ?
                ")->execute([
                    $messageId,
                    json_encode($retorno, JSON_UNESCAPED_UNICODE),
                    $item['DMI_ID']
                ]);

                $disparoModel->salvar([
                    'cliente' => $item['CLI_ID'],
                    'meta' => $item['MTA_ID'],
                    'template_id' => $item['TMP_ID'],
                    'numero' => $item['DMI_Numero'],
                    'template' => $item['TMP_Nome'],
                    'variaveis' => $variaveis,
                    'message_id' => $messageId,
                    'status' => 'aguardando_confirmacao',
                    'retorno' => $retorno
                ]);

                $consumo->registrarMensagem($item['CLI_ID']);
                $controlePlano->registrarUso($item['CLI_ID']);

                $conversaId = $conversaModel->buscarOuCriar(
                    $item['CLI_ID'],
                    $item['MTA_ID'],
                    $item['DMI_Numero'],
                    null
                );

                $conversaModel->salvarMensagem([
                    'conversa_id' => $conversaId,
                    'direcao' => 'enviada',
                    'tipo' => 'template',
                    'texto' => $item['TMP_Nome'],
                    'message_id' => $messageId,
                    'status' => 'aguardando_confirmacao',
                    'retorno' => $retorno,
                    'data_mensagem' => date('Y-m-d H:i:s')
                ]);
            }else{
                registrarErroDisparoManual($db, $item['DMI_ID'], extrairErroMetaWorker($retorno), $retorno);
            }

        }catch(Exception $e){
            registrarErroDisparoManual($db, $item['DMI_ID'], $e->getMessage());
        }

        recalcularLoteManual($db, $item['DML_ID']);
        aplicarLimiteEnvio($retorno ?? null);
    }

    finalizarLotesManuaisConcluidos($db);
}

function registrarErroDisparoManual($db, $itemId, $erro, $retorno = null)
{
    $db->prepare("
        UPDATE disparo_manual_itens
        SET
            DMI_Status = 'erro',
            DMI_Erro = ?,
            DMI_Retorno = ?,
            DMI_DataAtualizacao = NOW()
        WHERE DMI_ID = ?
    ")->execute([
        $erro,
        json_encode($retorno, JSON_UNESCAPED_UNICODE),
        $itemId
    ]);
}

function recalcularLoteManual($db, $loteId)
{
    $stmt = $db->prepare("
        SELECT
            COUNT(*) total,
            SUM(CASE WHEN DMI_Status IN ('aguardando_confirmacao','enviado','entregue','lido') THEN 1 ELSE 0 END) enviados,
            SUM(CASE WHEN DMI_Status = 'erro' THEN 1 ELSE 0 END) erros,
            SUM(CASE WHEN DMI_Status IN ('pendente','processando') THEN 1 ELSE 0 END) pendentes
        FROM disparo_manual_itens
        WHERE DML_ID = ?
    ");

    $stmt->execute([$loteId]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $status = ((int) ($dados['pendentes'] ?? 0) > 0) ? 'processando' : 'concluido';

    $db->prepare("
        UPDATE disparo_manual_lotes
        SET
            DML_Total = ?,
            DML_TotalEnviados = ?,
            DML_TotalErros = ?,
            DML_Status = ?,
            DML_DataAtualizacao = NOW(),
            DML_DataConclusao = CASE WHEN ? = 'concluido' THEN NOW() ELSE DML_DataConclusao END
        WHERE DML_ID = ?
    ")->execute([
        (int) ($dados['total'] ?? 0),
        (int) ($dados['enviados'] ?? 0),
        (int) ($dados['erros'] ?? 0),
        $status,
        $status,
        $loteId
    ]);
}

function finalizarLotesManuaisConcluidos($db)
{
    $stmt = $db->query("
        SELECT DML_ID
        FROM disparo_manual_lotes
        WHERE DML_Status IN ('pendente','processando')
    ");

    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $lote){
        recalcularLoteManual($db, $lote['DML_ID']);
    }
}
