<?php

namespace Services;

use Core\Database;
use Models\Conversa;
use Models\ConsumoMensal;
use PDO;
use Exception;

class CampanhaQueueService
{
    private $db;
    private $modoTeste;
    private $validator;
    private $consumo;
    private $controlePlano;
    private $conversaModel;
    private $retryPolicy;
    private $metaCache = [];

    public function __construct($modoTeste = false, ?WorkerOperationalValidatorService $validator = null)
    {
        $this->db = Database::getInstance();
        $this->modoTeste = (bool) $modoTeste;
        $this->validator = $validator ?: new WorkerOperationalValidatorService();
        $this->consumo = new ConsumoMensal();
        $this->controlePlano = new ControlePlanoService();
        $this->conversaModel = new Conversa();
        $this->retryPolicy = new WorkerRetryPolicyService();
    }

    public function processar(int $limitePorExecucao = 50, string $workerId = ''): array
    {
        $limitePorExecucao = $this->normalizarLimite($limitePorExecucao, 50);
        $resumo = $this->resumoInicial();

        $campanhasProntas = $this->ativarCampanhasAgendadas();
        $resumo['campanhas_encontradas'] = $campanhasProntas;

        $campanhas = $this->buscarCampanhasProcessando();
        $resumo['processadas'] = count($campanhas);

        foreach($campanhas as $campanha){
            try{
                $resultado = $this->processarCampanha($campanha, $limitePorExecucao, $workerId);
                $resumo = $this->somarResumo($resumo, $resultado);
            }catch(Exception $e){
                $resumo['excecoes']++;
                $resumo['mensagens'][] = 'Campanha ' . ($campanha['CAM_ID'] ?? '-') . ': ' . $this->sanitizarMensagem($e->getMessage());
            }
        }

        return $resumo;
    }

    public function recuperarTravados(int $timeoutMinutos = 15): int
    {
        $timeoutMinutos = max(5, $timeoutMinutos);

        $stmt = $this->db->prepare("
            UPDATE fila_envio
            SET
                FIL_Status = 'pendente',
                FIL_WorkerId = NULL,
                FIL_DataReserva = NULL,
                FIL_DataAtualizacao = NOW(),
                FIL_ProximaTentativa = NOW(),
                FIL_UltimoErroTipo = 'recuperado_timeout',
                FIL_UltimoErroCodigo = 'processing_timeout'
            WHERE FIL_Status = 'processando'
            AND FIL_MessageId IS NULL
            AND FIL_DataReserva IS NOT NULL
            AND FIL_DataReserva < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");

        $stmt->execute([$timeoutMinutos]);

        return (int) $stmt->rowCount();
    }

    private function ativarCampanhasAgendadas(): int
    {
        $stmt = $this->db->prepare("
            UPDATE campanhas
            SET CAM_Status = 'processando'
            WHERE CAM_Status = 'agendada'
            AND CAM_DataAgendamento <= NOW()
        ");

        $stmt->execute();

        return (int) $stmt->rowCount();
    }

    private function buscarCampanhasProcessando(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM campanhas
            WHERE CAM_Status = 'processando'
            ORDER BY CAM_DataAgendamento ASC, CAM_ID ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function processarCampanha(array $campanha, int $limitePorExecucao, string $workerId): array
    {
        $resumo = $this->resumoInicial();
        $template = $this->buscarTemplate((int) $campanha['TMP_ID']);

        if(!$template){
            $this->cancelarCampanha((int) $campanha['CAM_ID']);
            $resumo['erros_definitivos']++;
            return $resumo;
        }

        $variaveis = $this->buscarVariaveis((int) $campanha['CAM_ID']);
        $itens = $this->buscarItensPendentes((int) $campanha['CAM_ID'], $limitePorExecucao);

        if(empty($itens)){
            $this->finalizarSeConcluida((int) $campanha['CAM_ID']);
            return $resumo;
        }

        foreach($itens as $item){
            if(!$this->reservarItem($item, $workerId)){
                continue;
            }

            $resumo['reservados']++;

            try{
                $validacao = $this->validator->validarEnvio(
                    (int) $campanha['CLI_ID'],
                    (int) $template['MTA_ID'],
                    (string) $item['CON_Telefone']
                );

                if(!$validacao['permitido']){
                    $this->registrarBloqueio($campanha, $item, $validacao);
                    $resumo['bloqueados']++;
                    continue;
                }

                $parametros = $this->montarParametros($item, $variaveis);
                $retorno = $this->enviarItem($campanha, $template, $item, $parametros);
                $resultado = $this->normalizarResultadoEnvio($retorno);

                if($resultado['sucesso']){
                    try{
                        $this->registrarSucesso($campanha, $template, $item, $parametros, $retorno, $resultado['message_id']);
                        $resumo['enviados']++;
                    }catch(Exception $e){
                        $this->registrarPersistenciaPosEnvio($item, $resultado['message_id'], $e->getMessage());
                        $resumo['erros_definitivos']++;
                    }
                }else{
                    $tentativas = ((int) ($item['FIL_Tentativas'] ?? 0)) + 1;
                    $this->registrarFalhaEnvio($campanha, $item, $resultado, $retorno, $tentativas);

                    if($resultado['retry'] && !$this->retryPolicy->atingiuMaximo($tentativas)){
                        $resumo['erros_temporarios']++;
                    }else{
                        $resumo['erros_definitivos']++;
                    }
                }
            }catch(Exception $e){
                $this->registrarErro($campanha, $item, $this->sanitizarMensagem($e->getMessage()));
                $resumo['erros_definitivos']++;
            }

            $this->aplicarLimiteEnvio($retorno ?? null);
        }

        $this->finalizarSeConcluida((int) $campanha['CAM_ID']);

        return $resumo;
    }

    private function buscarTemplate(int $templateId)
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM templates_meta
            WHERE TMP_ID = ?
            LIMIT 1
        ");

        $stmt->execute([$templateId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function buscarVariaveis(int $campanhaId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM campanha_variaveis
            WHERE CAM_ID = ?
            ORDER BY CAST(CPV_Variavel AS UNSIGNED) ASC
        ");

        $stmt->execute([$campanhaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buscarItensPendentes(int $campanhaId, int $limite): array
    {
        $stmt = $this->db->prepare("
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
            AND (f.FIL_ProximaTentativa IS NULL OR f.FIL_ProximaTentativa <= NOW())
            ORDER BY f.FIL_ID ASC
            LIMIT {$limite}
        ");

        $stmt->execute([$campanhaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function reservarItem(array $item, string $workerId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE fila_envio
            SET
                FIL_Status = 'processando',
                FIL_WorkerId = ?,
                FIL_DataReserva = NOW(),
                FIL_DataAtualizacao = NOW(),
                FIL_ProximaTentativa = NULL,
                FIL_Tentativas = FIL_Tentativas + 1
            WHERE FIL_ID = ?
            AND FIL_Status = 'pendente'
            AND (FIL_ProximaTentativa IS NULL OR FIL_ProximaTentativa <= NOW())
        ");

        $stmt->execute([$workerId, $item['FIL_ID']]);

        return $stmt->rowCount() === 1;
    }

    private function montarParametros(array $item, array $variaveis): array
    {
        $dadosContato = json_decode($item['CON_DadosJson'] ?? '[]', true);

        if(!is_array($dadosContato)){
            $dadosContato = [];
        }

        $parametros = [];

        foreach($variaveis as $var){
            $campo = $var['CPV_Campo'];
            $parametros[$var['CPV_Variavel']] = $dadosContato[$campo] ?? '';
        }

        return $parametros;
    }

    private function enviarItem(array $campanha, array $template, array $item, array $parametros): array
    {
        if($this->modoTeste){
            return [
                'messages' => [
                    ['id' => 'SIMULACAO_CAMPANHA_' . $item['FIL_ID']]
                ]
            ];
        }

        $metaId = (int) $template['MTA_ID'];
        $clienteId = (int) $campanha['CLI_ID'];
        $cacheKey = $metaId . ':' . $clienteId;

        if(empty($this->metaCache[$cacheKey])){
            $this->metaCache[$cacheKey] = new MetaService($metaId, $clienteId);
        }

        return $this->metaCache[$cacheKey]->enviarTemplate(
            $item['CON_Telefone'],
            $template,
            $parametros,
            $this->midiaHeaderCampanha($campanha)
        );
    }

    private function registrarSucesso(array $campanha, array $template, array $item, array $parametros, array $retorno, string $messageId): void
    {
        $this->db->prepare("
            UPDATE fila_envio
            SET
                FIL_Status = 'enviado',
                FIL_DataEnvio = NOW(),
                FIL_DataAtualizacao = NOW(),
                FIL_WorkerId = NULL,
                FIL_DataReserva = NULL,
                FIL_ProximaTentativa = NULL,
                FIL_UltimoErroTipo = NULL,
                FIL_UltimoErroCodigo = NULL,
                FIL_Erro = NULL,
                FIL_MessageId = ?,
                FIL_Retorno = ?
            WHERE FIL_ID = ?
        ")->execute([
            $messageId,
            json_encode($this->retornoSeguro($retorno), JSON_UNESCAPED_UNICODE),
            $item['FIL_ID']
        ]);

        $this->db->prepare("
            UPDATE campanhas
            SET CAM_TotalEnviados = CAM_TotalEnviados + 1
            WHERE CAM_ID = ?
        ")->execute([$campanha['CAM_ID']]);

        $this->consumo->registrarMensagem((int) $campanha['CLI_ID']);
        $this->controlePlano->registrarUso((int) $campanha['CLI_ID']);

        $conversaId = $this->conversaModel->buscarOuCriar(
            (int) $campanha['CLI_ID'],
            (int) $template['MTA_ID'],
            $item['CON_Telefone'],
            $item['CON_Nome']
        );

        $this->conversaModel->salvarMensagem([
            'conversa_id' => $conversaId,
            'direcao' => 'enviada',
            'tipo' => 'template',
            'texto' => $template['TMP_Nome'],
            'message_id' => $messageId,
            'status' => 'enviado',
            'retorno' => $this->retornoSeguro($retorno),
            'data_mensagem' => date('Y-m-d H:i:s')
        ]);
    }

    private function registrarBloqueio(array $campanha, array $item, array $validacao): void
    {
        $codigo = $validacao['codigo'] ?? 'bloqueio_operacional';
        $mensagem = '[' . $codigo . '] ' . ($validacao['mensagem'] ?? 'Envio bloqueado por validação operacional.');
        $retorno = ['tipo' => 'bloqueio_operacional', 'status' => $validacao['status'] ?? null, 'codigo' => $codigo];

        if(($validacao['status'] ?? '') === WorkerRetryPolicyService::BLOQUEIO_TEMPORARIO){
            $tentativasAtuais = (int) ($item['FIL_Tentativas'] ?? 0);
            $proximaTentativaSql = $this->retryPolicy->proximaTentativaSql(max(1, $tentativasAtuais));
            $this->db->prepare("
                UPDATE fila_envio
                SET
                    FIL_Status = 'pendente',
                    FIL_WorkerId = NULL,
                    FIL_DataReserva = NULL,
                    FIL_DataAtualizacao = NOW(),
                    FIL_ProximaTentativa = {$proximaTentativaSql},
                    FIL_Tentativas = GREATEST(FIL_Tentativas - 1, 0),
                    FIL_UltimoErroTipo = ?,
                    FIL_UltimoErroCodigo = ?,
                    FIL_Erro = ?,
                    FIL_Retorno = ?
                WHERE FIL_ID = ?
            ")->execute([
                WorkerRetryPolicyService::BLOQUEIO_TEMPORARIO,
                $codigo,
                $this->sanitizarMensagem($mensagem),
                json_encode($retorno, JSON_UNESCAPED_UNICODE),
                $item['FIL_ID']
            ]);
            return;
        }

        $this->registrarErro($campanha, $item, $mensagem, $retorno, WorkerRetryPolicyService::BLOQUEIO_DEFINITIVO, $codigo);
    }

    private function registrarErro(array $campanha, array $item, string $erro, $retorno = null, string $tipoErro = null, ?string $codigoErro = null): void
    {
        $this->db->prepare("
            UPDATE fila_envio
            SET
                FIL_Status = 'erro',
                FIL_WorkerId = NULL,
                FIL_DataReserva = NULL,
                FIL_DataAtualizacao = NOW(),
                FIL_ProximaTentativa = NULL,
                FIL_UltimoErroTipo = ?,
                FIL_UltimoErroCodigo = ?,
                FIL_Erro = ?,
                FIL_Retorno = ?
            WHERE FIL_ID = ?
        ")->execute([
            $tipoErro ?: WorkerRetryPolicyService::ERRO_DEFINITIVO,
            $codigoErro,
            $this->sanitizarMensagem($erro),
            json_encode($this->retornoSeguro($retorno), JSON_UNESCAPED_UNICODE),
            $item['FIL_ID']
        ]);

        $this->db->prepare("
            UPDATE campanhas
            SET CAM_TotalErros = CAM_TotalErros + 1
            WHERE CAM_ID = ?
        ")->execute([$campanha['CAM_ID']]);
    }

    private function finalizarSeConcluida(int $campanhaId): void
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) total
            FROM fila_envio
            WHERE CAM_ID = ?
            AND (
                FIL_Status IN ('pendente','processando')
                OR (FIL_ProximaTentativa IS NOT NULL AND FIL_ProximaTentativa > NOW())
            )
        ");

        $stmt->execute([$campanhaId]);
        $total = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        if($total === 0){
            $this->db->prepare("
                UPDATE campanhas
                SET CAM_Status = 'finalizada'
                WHERE CAM_ID = ?
            ")->execute([$campanhaId]);
        }
    }

    private function cancelarCampanha(int $campanhaId): void
    {
        $this->db->prepare("
            UPDATE campanhas
            SET CAM_Status = 'cancelada'
            WHERE CAM_ID = ?
        ")->execute([$campanhaId]);
    }

    private function normalizarResultadoEnvio($retorno): array
    {
        return $this->retryPolicy->classificarRetorno($retorno);
    }

    private function registrarFalhaEnvio(array $campanha, array $item, array $resultado, $retorno, int $tentativas): void
    {
        if($resultado['retry'] && !$this->retryPolicy->atingiuMaximo($tentativas)){
            $proximaTentativaSql = $this->retryPolicy->proximaTentativaSql($tentativas);
            $this->db->prepare("
                UPDATE fila_envio
                SET
                    FIL_Status = 'pendente',
                    FIL_WorkerId = NULL,
                    FIL_DataReserva = NULL,
                    FIL_DataAtualizacao = NOW(),
                    FIL_ProximaTentativa = {$proximaTentativaSql},
                    FIL_UltimoErroTipo = ?,
                    FIL_UltimoErroCodigo = ?,
                    FIL_Erro = ?,
                    FIL_Retorno = ?
                WHERE FIL_ID = ?
            ")->execute([
                WorkerRetryPolicyService::ERRO_TEMPORARIO,
                $resultado['erro_codigo'],
                $this->sanitizarMensagem($resultado['erro_mensagem'] ?? 'Falha temporária ao enviar mensagem.'),
                json_encode($this->retornoSeguro($retorno), JSON_UNESCAPED_UNICODE),
                $item['FIL_ID']
            ]);
            return;
        }

        $codigo = $resultado['retry'] ? 'max_attempts' : ($resultado['erro_codigo'] ?? null);
        $mensagem = $resultado['retry']
            ? 'Tentativa máxima atingida. ' . ($resultado['erro_mensagem'] ?? '')
            : ($resultado['erro_mensagem'] ?? 'Erro definitivo ao enviar mensagem.');

        $this->registrarErro(
            $campanha,
            $item,
            $mensagem,
            $retorno,
            WorkerRetryPolicyService::ERRO_DEFINITIVO,
            $codigo
        );
    }

    private function registrarPersistenciaPosEnvio(array $item, string $messageId, string $erro): void
    {
        try{
            $this->db->prepare("
                UPDATE fila_envio
                SET
                    FIL_MessageId = COALESCE(FIL_MessageId, ?),
                    FIL_Status = 'processando',
                    FIL_DataAtualizacao = NOW(),
                    FIL_UltimoErroTipo = ?,
                    FIL_UltimoErroCodigo = ?,
                    FIL_Erro = ?
                WHERE FIL_ID = ?
            ")->execute([
                $messageId,
                WorkerRetryPolicyService::ERRO_PERSISTENCIA_POS_ENVIO,
                'persistencia_pos_envio',
                $this->sanitizarMensagem($erro),
                $item['FIL_ID']
            ]);
        }catch(Exception $e){
            error_log('worker persistencia_pos_envio fila_envio FIL_ID=' . ($item['FIL_ID'] ?? '') . ' message_id=' . $messageId . ' erro=' . $this->sanitizarMensagem($e->getMessage()));
        }
    }

    private function midiaHeaderCampanha(array $campanha)
    {
        if(empty($campanha['CAM_HeaderMidiaId'])){
            return null;
        }

        return [
            'tipo' => $campanha['CAM_HeaderMidiaTipo'] ?? null,
            'media_id' => $campanha['CAM_HeaderMidiaId'],
            'filename' => $campanha['CAM_HeaderMidiaNome'] ?? null,
            'mime' => $campanha['CAM_HeaderMidiaMime'] ?? null,
            'tamanho' => $campanha['CAM_HeaderMidiaTamanho'] ?? null
        ];
    }

    private function aplicarLimiteEnvio($retorno = null): void
    {
        if($this->ehRateLimitMeta($retorno)){
            sleep((int) WHATSAPP_PAUSA_RATE_LIMIT_SEGUNDOS);
            return;
        }

        $enviosPorSegundo = max(1, (int) WHATSAPP_ENVIOS_POR_SEGUNDO);
        usleep((int) round(1000000 / $enviosPorSegundo));
    }

    private function ehRateLimitMeta($retorno): bool
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

    private function extrairErroMeta($retorno): string
    {
        if($this->ehRateLimitMeta($retorno)){
            return 'Limite de envio da Meta atingido. O lote foi pausado temporariamente e deve ser retomado com velocidade reduzida.';
        }

        if(is_array($retorno) && !empty($retorno['error']['message'])){
            return $this->sanitizarMensagem($retorno['error']['message']);
        }

        return is_array($retorno)
            ? $this->sanitizarMensagem(json_encode($retorno, JSON_UNESCAPED_UNICODE))
            : 'Erro ao enviar mensagem';
    }

    private function retornoSeguro($retorno)
    {
        if(!is_array($retorno)){
            return $retorno;
        }

        $seguro = $retorno;
        unset($seguro['payload']);

        return $seguro;
    }

    private function sanitizarMensagem(string $mensagem): string
    {
        $mensagem = preg_replace('/(access_token|token|authorization|bearer|senha|password|secret)[^,;\s]*/i', '$1=***', $mensagem);
        $mensagem = preg_replace('/[\r\n\t]+/', ' ', $mensagem);

        return trim(substr($mensagem, 0, 600));
    }

    private function resumoInicial(): array
    {
        return [
            'campanhas_encontradas' => 0,
            'processadas' => 0,
            'reservados' => 0,
            'enviados' => 0,
            'erros_temporarios' => 0,
            'erros_definitivos' => 0,
            'bloqueados' => 0,
            'excecoes' => 0,
            'mensagens' => []
        ];
    }

    private function somarResumo(array $base, array $adicional): array
    {
        foreach(['reservados', 'enviados', 'erros_temporarios', 'erros_definitivos', 'bloqueados', 'excecoes'] as $campo){
            $base[$campo] += (int) ($adicional[$campo] ?? 0);
        }

        $base['mensagens'] = array_merge($base['mensagens'], $adicional['mensagens'] ?? []);

        return $base;
    }

    private function normalizarLimite(int $limite, int $padrao): int
    {
        if($limite <= 0){
            return $padrao;
        }

        return max(1, min($limite, 100));
    }
}
