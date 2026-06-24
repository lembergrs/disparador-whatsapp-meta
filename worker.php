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

$modoTeste = false; // troque para false para envio real
$limitePorExecucao = 50;

$db = Database::getInstance();

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
