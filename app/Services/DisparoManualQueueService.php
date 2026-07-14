<?php

namespace Services;

use Core\Database;
use Models\Conversa;
use Models\ConsumoMensal;
use Models\Disparo;
use PDO;
use Exception;

class DisparoManualQueueService
{
    private $db;
    private $modoTeste;
    private $metaCache = [];
    private $disparoModel;
    private $conversaModel;
    private $consumo;
    private $controlePlano;
    private $validator;
    private $retryPolicy;

    public function __construct($modoTeste = false)
    {
        $this->db = Database::getInstance();
        $this->modoTeste = (bool) $modoTeste;
        $this->disparoModel = new Disparo();
        $this->conversaModel = new Conversa();
        $this->consumo = new ConsumoMensal();
        $this->controlePlano = new ControlePlanoService();
        $this->validator = new WorkerOperationalValidatorService();
        $this->retryPolicy = new WorkerRetryPolicyService();
    }

    public function processarLote(int $clienteId, int $loteId, int $limite = 5, string $origem = 'ajax')
    {
        $limite = $this->normalizarLimite($limite, 5);
        $lote = $this->buscarLoteCliente($clienteId, $loteId);

        if(!$lote){
            throw new Exception('Lote não encontrado.');
        }

        if(!in_array($lote['DML_Status'], ['pendente', 'processando'], true)){
            return $this->montarResumo($loteId, [
                'processados' => 0,
                'reservados' => 0,
                'pulados' => 0,
                'aceitos' => 0,
                'erros' => 0,
                'origem' => $origem
            ]);
        }

        $this->marcarLoteProcessando($loteId, $clienteId);
        $resultado = $this->processarItens($limite, $clienteId, $loteId, $origem);
        $this->recalcularLote($loteId);

        return $this->montarResumo($loteId, $resultado);
    }

    public function processarPendentes(int $limite = 20, string $origem = 'cron', string $workerId = '')
    {
        $limite = $this->normalizarLimite($limite, 20);

        $this->db->query("
            UPDATE disparo_manual_lotes
            SET DML_Status = 'processando', DML_DataAtualizacao = NOW()
            WHERE DML_Status = 'pendente'
        ");

        $resultado = $this->processarItens($limite, null, null, $origem, $workerId);
        $this->finalizarLotesConcluidos();

        return $resultado;
    }

    private function processarItens(int $limite, ?int $clienteId, ?int $loteId, string $origem, string $workerId = '')
    {
        $itens = $this->buscarItensPendentes($limite, $clienteId, $loteId, $origem);

        $resultado = [
            'processados' => 0,
            'reservados' => 0,
            'pulados' => 0,
            'aceitos' => 0,
            'erros' => 0,
            'bloqueados' => 0,
            'origem' => $origem,
            'worker_id' => $workerId
        ];

        foreach($itens as $item){
            $item['worker_id'] = $workerId;
            $item['origem'] = $origem;

            if(!$this->reservarItem($item)){
                $resultado['pulados']++;
                continue;
            }

            $resultado['reservados']++;
            $resultado['processados']++;

            $retorno = null;

            try{
                if($origem !== 'ajax'){
                    $validacao = $this->validator->validarEnvio(
                        (int) $item['CLI_ID'],
                        (int) $item['MTA_ID'],
                        (string) $item['DMI_Numero']
                    );

                    if(!$validacao['permitido']){
                        $this->registrarBloqueioOperacional($item['DMI_ID'], $validacao);
                        $resultado['bloqueados']++;
                        $this->recalcularLote((int) $item['DML_ID']);
                        continue;
                    }
                }

                $variaveis = json_decode($item['DMI_VariaveisJson'] ?? '[]', true);

                if(!is_array($variaveis)){
                    $variaveis = [];
                }

                $retorno = $this->enviarItem($item, $variaveis);

                $resultadoEnvio = $this->normalizarResultadoEnvio($retorno);

                if($resultadoEnvio['sucesso']){
                    try{
                        $this->registrarSucesso($item, $variaveis, $retorno);
                        $resultado['aceitos']++;
                    }catch(Exception $e){
                        $this->registrarPersistenciaPosEnvio($item, $resultadoEnvio['message_id'], $e->getMessage());
                        $resultado['erros']++;
                    }
                }else{
                    $tentativas = ((int) ($item['DMI_Tentativas'] ?? 0)) + 1;
                    $this->registrarFalhaEnvio($item, $resultadoEnvio, $retorno, $tentativas);
                    $resultado['erros']++;
                }
            }catch(Exception $e){
                $this->registrarErro($item['DMI_ID'], $e->getMessage());
                $resultado['erros']++;
            }

            $this->recalcularLote((int) $item['DML_ID']);
            $this->aplicarLimiteEnvio($retorno);
        }

        return $resultado;
    }


    public function recuperarTravados(int $timeoutMinutos = 15): int
    {
        $timeoutMinutos = max(5, $timeoutMinutos);

        $stmt = $this->db->prepare("
            UPDATE disparo_manual_itens
            SET
                DMI_Status = 'pendente',
                DMI_WorkerId = NULL,
                DMI_DataReserva = NULL,
                DMI_ProximaTentativa = NOW(),
                DMI_UltimoErroTipo = 'recuperado_timeout',
                DMI_UltimoErroCodigo = 'processing_timeout',
                DMI_DataAtualizacao = NOW()
            WHERE DMI_Status = 'processando'
            AND DMI_MessageId IS NULL
            AND DMI_DataReserva IS NOT NULL
            AND DMI_DataReserva < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");

        $stmt->execute([$timeoutMinutos]);

        return (int) $stmt->rowCount();
    }

    private function buscarItensPendentes(int $limite, ?int $clienteId, ?int $loteId, string $origem = 'cron')
    {
        $where = [
            "i.DMI_Status = 'pendente'",
            "l.DML_Status IN ('pendente','processando')"
        ];

        if($origem !== 'ajax'){
            $where[] = "(i.DMI_ProximaTentativa IS NULL OR i.DMI_ProximaTentativa <= NOW())";
        }
        $params = [];

        if($clienteId !== null){
            $where[] = 'i.CLI_ID = ?';
            $params[] = $clienteId;
        }

        if($loteId !== null){
            $where[] = 'i.DML_ID = ?';
            $params[] = $loteId;
        }

        $stmt = $this->db->prepare("
            SELECT
                i.*,
                l.MTA_ID,
                l.TMP_ID,
                t.*
            FROM disparo_manual_itens i
            INNER JOIN disparo_manual_lotes l ON l.DML_ID = i.DML_ID
            INNER JOIN templates_meta t ON t.TMP_ID = l.TMP_ID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.DMI_ID ASC
            LIMIT {$limite}
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function reservarItem(array $item)
    {
        if(($item['origem'] ?? '') === 'ajax'){
            $stmt = $this->db->prepare("
                UPDATE disparo_manual_itens
                SET
                    DMI_Status = 'processando',
                    DMI_WorkerId = ?,
                    DMI_DataReserva = NOW(),
                    DMI_ProximaTentativa = NULL,
                    DMI_DataAtualizacao = NOW()
                WHERE DMI_ID = ?
                AND CLI_ID = ?
                AND DMI_Status = 'pendente'
            ");

            $stmt->execute([
                $item['worker_id'] ?? '',
                $item['DMI_ID'],
                $item['CLI_ID']
            ]);

            return $stmt->rowCount() === 1;
        }

        $stmt = $this->db->prepare("
            UPDATE disparo_manual_itens
            SET
                DMI_Status = 'processando',
                DMI_WorkerId = ?,
                DMI_DataReserva = NOW(),
                DMI_ProximaTentativa = NULL,
                DMI_Tentativas = DMI_Tentativas + 1,
                DMI_DataAtualizacao = NOW()
            WHERE DMI_ID = ?
            AND CLI_ID = ?
            AND DMI_Status = 'pendente'
            AND (DMI_ProximaTentativa IS NULL OR DMI_ProximaTentativa <= NOW())
        ");

        $stmt->execute([
            $item['worker_id'] ?? '',
            $item['DMI_ID'],
            $item['CLI_ID']
        ]);

        return $stmt->rowCount() === 1;
    }

    private function enviarItem(array $item, array $variaveis)
    {
        if($this->modoTeste){
            return [
                'messages' => [
                    ['id' => 'SIMULACAO_MANUAL_' . $item['DMI_ID']]
                ]
            ];
        }

        $metaKey = $item['MTA_ID'] . ':' . $item['CLI_ID'];

        if(empty($this->metaCache[$metaKey])){
            $this->metaCache[$metaKey] = new MetaService($item['MTA_ID'], $item['CLI_ID']);
        }

        return $this->metaCache[$metaKey]->enviarTemplate(
            $item['DMI_Numero'],
            $item,
            $variaveis,
            $this->midiaHeaderItem($item)
        );
    }


    private function midiaHeaderItem(array $item)
    {
        if(empty($item['DML_HeaderMidiaId'])){
            return null;
        }

        return [
            'tipo' => $item['DML_HeaderMidiaTipo'] ?? null,
            'media_id' => $item['DML_HeaderMidiaId'],
            'filename' => $item['DML_HeaderMidiaNome'] ?? null,
            'mime' => $item['DML_HeaderMidiaMime'] ?? null,
            'tamanho' => $item['DML_HeaderMidiaTamanho'] ?? null
        ];
    }

    private function registrarSucesso(array $item, array $variaveis, array $retorno)
    {
        $messageId = $retorno['messages'][0]['id'];

        $this->db->prepare("
            UPDATE disparo_manual_itens
            SET
                DMI_Status = 'aguardando_confirmacao',
                DMI_MessageId = ?,
                DMI_Retorno = ?,
                DMI_Erro = NULL,
                DMI_WorkerId = NULL,
                DMI_DataReserva = NULL,
                DMI_ProximaTentativa = NULL,
                DMI_UltimoErroTipo = NULL,
                DMI_UltimoErroCodigo = NULL,
                DMI_DataEnvio = NOW(),
                DMI_DataAtualizacao = NOW()
            WHERE DMI_ID = ?
        ")->execute([
            $messageId,
            json_encode($retorno, JSON_UNESCAPED_UNICODE),
            $item['DMI_ID']
        ]);

        $this->disparoModel->salvar([
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

        $this->consumo->registrarMensagem($item['CLI_ID']);
        $this->controlePlano->registrarUso($item['CLI_ID']);

        $conversaId = $this->conversaModel->buscarOuCriar(
            $item['CLI_ID'],
            $item['MTA_ID'],
            $item['DMI_Numero'],
            null
        );

        $this->conversaModel->salvarMensagem([
            'conversa_id' => $conversaId,
            'direcao' => 'enviada',
            'tipo' => 'template',
            'texto' => $item['TMP_Nome'],
            'message_id' => $messageId,
            'status' => 'aguardando_confirmacao',
            'retorno' => $retorno,
            'data_mensagem' => date('Y-m-d H:i:s')
        ]);
    }


    private function registrarBloqueioOperacional($itemId, array $validacao)
    {
        $erro = $this->mensagemBloqueioOperacional($validacao);
        $retorno = ['tipo' => 'bloqueio_operacional', 'status' => $validacao['status'] ?? null, 'codigo' => $validacao['codigo'] ?? null];

        if(($validacao['status'] ?? '') === WorkerRetryPolicyService::BLOQUEIO_TEMPORARIO){
            $proximaTentativaSql = $this->retryPolicy->proximaTentativaSql(1);
            $this->db->prepare("
                UPDATE disparo_manual_itens
                SET
                    DMI_Status = 'pendente',
                    DMI_WorkerId = NULL,
                    DMI_DataReserva = NULL,
                    DMI_ProximaTentativa = {$proximaTentativaSql},
                    DMI_Tentativas = GREATEST(DMI_Tentativas - 1, 0),
                    DMI_UltimoErroTipo = ?,
                    DMI_UltimoErroCodigo = ?,
                    DMI_Erro = ?,
                    DMI_Retorno = ?,
                    DMI_DataAtualizacao = NOW()
                WHERE DMI_ID = ?
            ")->execute([
                WorkerRetryPolicyService::BLOQUEIO_TEMPORARIO,
                $validacao['codigo'] ?? null,
                $erro,
                json_encode($retorno, JSON_UNESCAPED_UNICODE),
                $itemId
            ]);
            return;
        }

        $this->registrarErro($itemId, $erro, $retorno, WorkerRetryPolicyService::BLOQUEIO_DEFINITIVO, $validacao['codigo'] ?? null);
    }

    private function registrarErro($itemId, $erro, $retorno = null, string $tipoErro = null, ?string $codigoErro = null)
    {
        $this->db->prepare("
            UPDATE disparo_manual_itens
            SET
                DMI_Status = 'erro',
                DMI_WorkerId = NULL,
                DMI_DataReserva = NULL,
                DMI_ProximaTentativa = NULL,
                DMI_UltimoErroTipo = ?,
                DMI_UltimoErroCodigo = ?,
                DMI_Erro = ?,
                DMI_Retorno = ?,
                DMI_DataAtualizacao = NOW()
            WHERE DMI_ID = ?
        ")->execute([
            $tipoErro ?: WorkerRetryPolicyService::ERRO_DEFINITIVO,
            $codigoErro,
            $erro,
            json_encode($retorno, JSON_UNESCAPED_UNICODE),
            $itemId
        ]);
    }

    private function buscarLoteCliente(int $clienteId, int $loteId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM disparo_manual_lotes
            WHERE DML_ID = ?
            AND CLI_ID = ?
            LIMIT 1
        ");

        $stmt->execute([$loteId, $clienteId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function marcarLoteProcessando(int $loteId, int $clienteId)
    {
        $this->db->prepare("
            UPDATE disparo_manual_lotes
            SET DML_Status = 'processando', DML_DataAtualizacao = NOW()
            WHERE DML_ID = ?
            AND CLI_ID = ?
            AND DML_Status = 'pendente'
        ")->execute([$loteId, $clienteId]);
    }

    private function recalcularLote($loteId)
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN DMI_Status IN ('aguardando_confirmacao','enviado','entregue','lido') THEN 1 ELSE 0 END) enviados,
                SUM(CASE WHEN DMI_Status IN ('erro','failed') THEN 1 ELSE 0 END) erros,
                SUM(CASE WHEN DMI_Status IN ('pendente','processando') THEN 1 ELSE 0 END) pendentes
            FROM disparo_manual_itens
            WHERE DML_ID = ?
        ");

        $stmt->execute([$loteId]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $status = ((int) ($dados['pendentes'] ?? 0) > 0) ? 'processando' : 'concluido';

        $this->db->prepare("
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

        return $status;
    }

    private function finalizarLotesConcluidos()
    {
        $stmt = $this->db->query("
            SELECT DML_ID
            FROM disparo_manual_lotes
            WHERE DML_Status IN ('pendente','processando')
        ");

        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $lote){
            $this->recalcularLote($lote['DML_ID']);
        }
    }

    private function montarResumo($loteId, array $resultado)
    {
        $stmt = $this->db->prepare("
            SELECT
                DML_ID,
                CLI_ID,
                DML_Total,
                DML_TotalEnviados,
                DML_TotalErros,
                DML_Status,
                DML_DataAtualizacao,
                DML_DataConclusao
            FROM disparo_manual_lotes
            WHERE DML_ID = ?
            LIMIT 1
        ");

        $stmt->execute([$loteId]);
        $lote = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) total,
                SUM(CASE WHEN DMI_Status IN ('aguardando_confirmacao','enviado','entregue','lido') THEN 1 ELSE 0 END) aceitos,
                SUM(CASE WHEN DMI_Status IN ('erro','failed') THEN 1 ELSE 0 END) erros,
                SUM(CASE WHEN DMI_Status IN ('pendente','processando') THEN 1 ELSE 0 END) pendentes
            FROM disparo_manual_itens
            WHERE DML_ID = ?
        ");

        $stmt->execute([$loteId]);
        $totais = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'lote' => $lote,
            'totais' => [
                'total' => (int) ($totais['total'] ?? 0),
                'aceitos' => (int) ($totais['aceitos'] ?? 0),
                'erros' => (int) ($totais['erros'] ?? 0),
                'pendentes' => (int) ($totais['pendentes'] ?? 0),
                'status_final' => $lote['DML_Status'] ?? null
            ],
            'bloco' => $resultado
        ];
    }

    private function normalizarLimite(int $limite, int $padrao)
    {
        if($limite <= 0){
            return $padrao;
        }

        return max(1, min($limite, 100));
    }

    private function aplicarLimiteEnvio($retorno = null)
    {
        if($this->ehRateLimitMeta($retorno)){
            sleep((int) WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS);
            return;
        }

        $enviosPorSegundo = max(1, (int) WHATSAPP_ENVIOS_POR_SEGUNDO);
        usleep((int) round(1000000 / $enviosPorSegundo));
    }

    private function ehRateLimitMeta($retorno)
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


    private function normalizarResultadoEnvio($retorno): array
    {
        return $this->retryPolicy->classificarRetorno($retorno);
    }

    private function registrarFalhaEnvio(array $item, array $resultado, $retorno, int $tentativas): void
    {
        if($resultado['retry'] && !$this->retryPolicy->atingiuMaximo($tentativas)){
            $proximaTentativaSql = $this->retryPolicy->proximaTentativaSql($tentativas);
            $this->db->prepare("
                UPDATE disparo_manual_itens
                SET
                    DMI_Status = 'pendente',
                    DMI_WorkerId = NULL,
                    DMI_DataReserva = NULL,
                    DMI_ProximaTentativa = {$proximaTentativaSql},
                    DMI_UltimoErroTipo = ?,
                    DMI_UltimoErroCodigo = ?,
                    DMI_Erro = ?,
                    DMI_Retorno = ?,
                    DMI_DataAtualizacao = NOW()
                WHERE DMI_ID = ?
            ")->execute([
                WorkerRetryPolicyService::ERRO_TEMPORARIO,
                $resultado['erro_codigo'],
                $resultado['erro_mensagem'],
                json_encode($retorno, JSON_UNESCAPED_UNICODE),
                $item['DMI_ID']
            ]);
            return;
        }

        $codigo = $resultado['retry'] ? 'max_attempts' : ($resultado['erro_codigo'] ?? null);
        $mensagem = $resultado['retry']
            ? 'Tentativa máxima atingida. ' . ($resultado['erro_mensagem'] ?? '')
            : ($resultado['erro_mensagem'] ?? 'Erro definitivo ao enviar mensagem.');

        $this->registrarErro($item['DMI_ID'], $mensagem, $retorno, WorkerRetryPolicyService::ERRO_DEFINITIVO, $codigo);
    }

    private function registrarPersistenciaPosEnvio(array $item, string $messageId, string $erro): void
    {
        try{
            $this->db->prepare("
                UPDATE disparo_manual_itens
                SET
                    DMI_MessageId = COALESCE(DMI_MessageId, ?),
                    DMI_Status = 'processando',
                    DMI_UltimoErroTipo = ?,
                    DMI_UltimoErroCodigo = ?,
                    DMI_Erro = ?,
                    DMI_DataAtualizacao = NOW()
                WHERE DMI_ID = ?
            ")->execute([
                $messageId,
                WorkerRetryPolicyService::ERRO_PERSISTENCIA_POS_ENVIO,
                'persistencia_pos_envio',
                $erro,
                $item['DMI_ID']
            ]);
        }catch(Exception $e){
            error_log('worker persistencia_pos_envio disparo_manual DMI_ID=' . ($item['DMI_ID'] ?? '') . ' message_id=' . $messageId . ' erro=' . $e->getMessage());
        }
    }

    private function mensagemBloqueioOperacional(array $validacao): string
    {
        $codigo = $validacao['codigo'] ?? 'bloqueio_operacional';
        $mensagem = $validacao['mensagem'] ?? 'Envio bloqueado por validação operacional.';

        return '[' . $codigo . '] ' . $mensagem;
    }

    private function extrairErroMeta($retorno)
    {
        if($this->ehRateLimitMeta($retorno)){
            return 'Limite de envio da Meta atingido. O lote foi pausado temporariamente e deve ser retomado com velocidade reduzida.';
        }

        if(is_array($retorno) && !empty($retorno['error']['message'])){
            return $retorno['error']['message'];
        }

        return is_array($retorno)
            ? json_encode($retorno, JSON_UNESCAPED_UNICODE)
            : 'Erro ao enviar mensagem';
    }
}
