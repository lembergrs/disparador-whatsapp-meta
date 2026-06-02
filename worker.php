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

$db = Database::getInstance();

$modoTeste = true;

$campanhas = $db->query("
    SELECT *
    FROM campanhas
    WHERE CAM_Status = 'agendada'
    AND CAM_DataAgendamento <= NOW()
")->fetchAll(PDO::FETCH_ASSOC);

foreach($campanhas as $campanha){

    echo "Processando campanha {$campanha['CAM_ID']}...\n";

    $db->prepare("
        UPDATE campanhas
        SET CAM_Status = 'processando'
        WHERE CAM_ID = ?
    ")->execute([
        $campanha['CAM_ID']
    ]);

    $template = $db->prepare("
        SELECT *
        FROM templates_meta
        WHERE TMP_ID = ?
    ");

    $template->execute([
        $campanha['TMP_ID']
    ]);

    $template = $template->fetch(PDO::FETCH_ASSOC);

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

    $metaId = $template['MTA_ID'];

    $meta = new MetaService($metaId);

    $variaveis = $db->prepare("
        SELECT *
        FROM campanha_variaveis
        WHERE CAM_ID = ?
        ORDER BY CPV_Variavel ASC
    ");

    $variaveis->execute([
        $campanha['CAM_ID']
    ]);

    $variaveis = $variaveis->fetchAll(PDO::FETCH_ASSOC);

    $fila = $db->prepare("
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
        LIMIT 20
    ");

    $fila->execute([
        $campanha['CAM_ID']
    ]);

    $itens = $fila->fetchAll(PDO::FETCH_ASSOC);

    foreach($itens as $item){

        $db->prepare("
            UPDATE fila_envio
            SET FIL_Status = 'processando',
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

            $campo =
                $var['CPV_Campo'];

            $parametros[] =
                $dadosContato[$campo]
                ?? '';

        }

        try{

            if($modoTeste){

                echo "\n";
                echo "Telefone: " .
                    $item['CON_Telefone'];
                echo "\n";

                print_r($parametros);

                echo "\n";

                $retorno = [
                    'messages' => [
                        [
                            'id' => 'SIMULACAO'
                        ]
                    ]
                ];

            }else{
                /*
                $retorno = $meta->enviarTemplate(
                    $item['CON_Telefone'],
                    $template,
                    $parametros
                );
                */
            }

            if(isset($retorno['messages'][0]['id'])){

                $db->prepare("
                    UPDATE fila_envio
                    SET FIL_Status = 'enviado',
                        FIL_DataEnvio = NOW(),
                        FIL_Erro = NULL
                    WHERE FIL_ID = ?
                ")->execute([
                    $item['FIL_ID']
                ]);

                $db->prepare("
                    UPDATE campanhas
                    SET CAM_TotalEnviados = CAM_TotalEnviados + 1
                    WHERE CAM_ID = ?
                ")->execute([
                    $campanha['CAM_ID']
                ]);

            }else{

                $erro =
                    $retorno['error']['message']
                    ?? json_encode($retorno);

                $db->prepare("
                    UPDATE fila_envio
                    SET FIL_Status = 'erro',
                        FIL_Erro = ?
                    WHERE FIL_ID = ?
                ")->execute([
                    $erro,
                    $item['FIL_ID']
                ]);

                $db->prepare("
                    UPDATE campanhas
                    SET CAM_TotalErros = CAM_TotalErros + 1
                    WHERE CAM_ID = ?
                ")->execute([
                    $campanha['CAM_ID']
                ]);
            }

        }catch(Exception $e){

            $db->prepare("
                UPDATE fila_envio
                SET FIL_Status = 'erro',
                    FIL_Erro = ?
                WHERE FIL_ID = ?
            ")->execute([
                $e->getMessage(),
                $item['FIL_ID']
            ]);

            $db->prepare("
                UPDATE campanhas
                SET CAM_TotalErros = CAM_TotalErros + 1
                WHERE CAM_ID = ?
            ")->execute([
                $campanha['CAM_ID']
            ]);
        }

        sleep(1);
    }

    $pendentes = $db->prepare("
        SELECT COUNT(*) total
        FROM fila_envio
        WHERE CAM_ID = ?
        AND FIL_Status IN ('pendente','processando')
    ");

    $pendentes->execute([
        $campanha['CAM_ID']
    ]);

    $pendentes =
        $pendentes->fetch(PDO::FETCH_ASSOC)['total'];

    if($pendentes == 0){

        $db->prepare("
            UPDATE campanhas
            SET CAM_Status = 'finalizada'
            WHERE CAM_ID = ?
        ")->execute([
            $campanha['CAM_ID']
        ]);

        echo "Campanha {$campanha['CAM_ID']} finalizada.\n";

    }else{

        echo "Campanha {$campanha['CAM_ID']} ainda possui {$pendentes} pendentes.\n";

    }

}