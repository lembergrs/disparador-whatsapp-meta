<?php

namespace Models;

use Core\Database;
use PDO;
use PDOException;

class MetaConta
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }





    public function listar()
    {
        $sql = $this->db->query("

            SELECT
                m.*,
                c.CLI_Nome

            FROM meta_contas m

            INNER JOIN clientes c
            ON c.CLI_ID = m.CLI_ID

            WHERE m.MTA_Ativo = 'S'

            ORDER BY m.MTA_ID DESC

        ");

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }





    private function colunaExiste($coluna)
    {
        try{

            $sql = $this->db->prepare("

                SELECT COUNT(*)

                FROM INFORMATION_SCHEMA.COLUMNS

                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'meta_contas'
                AND COLUMN_NAME = ?

            " );

            $sql->execute([
                $coluna
            ]);

            return (int) $sql->fetchColumn() > 0;

        }catch(PDOException $e){

            return false;
        }
    }

    public function colunaWebhookVerifyTokenExiste()
    {
        return $this->colunaExiste(
            'MTA_WebhookVerifyToken'
        );
    }

    public function colunasAutoRespostaExistem()
    {
        return
            $this->colunaExiste('MTA_AutoRespostaAtiva')
            &&
            $this->colunaExiste('MTA_AutoRespostaTexto')
            &&
            $this->colunaExiste('MTA_AutoRespostaIntervaloMinutos');
    }

    public function colunasCoexistenceExistem()
    {
        return
            $this->colunaExiste('MTA_OnboardingType')
            &&
            $this->colunaExiste('MTA_PlatformType');
    }

    public function reservarSyncUmaVez($id, $tipo)
    {
        $coluna = $tipo === 'contact' ? 'MTA_ContactSyncStatus' : 'MTA_HistorySyncStatus';
        if(!$this->colunaExiste($coluna)) throw new \RuntimeException('Migration operacional Coexistence não aplicada.');
        $sql = $this->db->prepare("UPDATE meta_contas SET {$coluna}='requesting', MTA_LastSyncEventAt=NOW() WHERE MTA_ID=? AND MTA_OnboardingType='coexistence' AND {$coluna} IS NULL");
        $sql->execute([(int)$id]);
        return $sql->rowCount() === 1;
    }

    public function reservarRetrySync($id, $tipo)
    {
        if(!in_array($tipo, ['contact', 'history'], true)){
            throw new \InvalidArgumentException('Tipo de sincronização Coexistence inválido.');
        }

        $statusColuna = $tipo === 'contact' ? 'MTA_ContactSyncStatus' : 'MTA_HistorySyncStatus';
        $requestColuna = $tipo === 'contact' ? 'MTA_ContactSyncRequestId' : 'MTA_HistorySyncRequestId';
        if(!$this->colunaExiste($statusColuna)) throw new \RuntimeException('Migration operacional Coexistence não aplicada.');

        $this->db->beginTransaction();
        try{
            $select = $this->db->prepare("SELECT MTA_OnboardingType, {$statusColuna} AS sync_status, {$requestColuna} AS request_id, MTA_LastSyncEventAt FROM meta_contas WHERE MTA_ID=? FOR UPDATE");
            $select->execute([(int) $id]);
            $estado = $select->fetch(PDO::FETCH_ASSOC);

            if(
                !$estado
                || ($estado['MTA_OnboardingType'] ?? null) !== 'coexistence'
                || !in_array($estado['sync_status'] ?? null, ['requested', 'request_failed'], true)
                || empty($estado['MTA_LastSyncEventAt'])
                || strtotime((string) $estado['MTA_LastSyncEventAt']) > time() - 900
            ){
                $this->db->rollBack();
                return null;
            }

            $update = $this->db->prepare("UPDATE meta_contas SET {$statusColuna}='requesting', MTA_LastSyncEventAt=NOW() WHERE MTA_ID=? AND MTA_OnboardingType='coexistence' AND {$statusColuna} IN ('requested','request_failed') AND MTA_LastSyncEventAt <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $update->execute([(int) $id]);
            if($update->rowCount() !== 1){
                $this->db->rollBack();
                return null;
            }

            $this->db->commit();
            return ['previous_request_id'=>(string) ($estado['request_id'] ?? '')];
        }catch(\Throwable $e){
            if($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function confirmarSyncSolicitado($id, $tipo, $requestId)
    {
        $requestColuna = $tipo === 'contact' ? 'MTA_ContactSyncRequestId' : 'MTA_HistorySyncRequestId';
        $statusColuna = $tipo === 'contact' ? 'MTA_ContactSyncStatus' : 'MTA_HistorySyncStatus';
        $sql = $this->db->prepare("UPDATE meta_contas SET {$requestColuna}=?, {$statusColuna}='requested', MTA_LastSyncEventAt=NOW() WHERE MTA_ID=? AND MTA_OnboardingType='coexistence' AND {$statusColuna}='requesting'");
        return $sql->execute([(string)$requestId,(int)$id]);
    }

    public function marcarSyncFalho($id, $tipo)
    {
        $coluna = $tipo === 'contact' ? 'MTA_ContactSyncStatus' : 'MTA_HistorySyncStatus';
        $sql = $this->db->prepare("UPDATE meta_contas SET {$coluna}='request_failed', MTA_LastSyncEventAt=NOW() WHERE MTA_ID=? AND {$coluna}='requesting'");
        return $sql->execute([(int)$id]);
    }

    public function buscarPorWabaIdAtiva($wabaId)
    {
        $sql = $this->db->prepare("SELECT * FROM meta_contas WHERE MTA_WabaId=? AND MTA_Ativo='S' ORDER BY MTA_ID DESC LIMIT 1");
        $sql->execute([(string)$wabaId]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function atualizarLifecycleCoexistence($id, $evento, array $dados = [])
    {
        $map = [
            'PARTNER_REMOVED'=>['desconectado','DISCONNECTED'],
            'ACCOUNT_OFFBOARDED'=>['desconectado','DISCONNECTED'],
            'ACCOUNT_RECONNECTED'=>['conectado','CONNECTED']
        ];
        if(!isset($map[$evento])) return false;
        [$status,$operacional] = $map[$evento];
        $reason = mb_substr(preg_replace('/[\r\n\t]+/', ' ', trim((string)($dados['reason'] ?? ''))), 0, 255, 'UTF-8');
        $initiated = mb_substr(preg_replace('/[^A-Za-z0-9_. -]/', '', (string)($dados['initiated_by'] ?? '')), 0, 100, 'UTF-8');
        $sql = $this->db->prepare("UPDATE meta_contas SET MTA_Status=?,MTA_OperationalStatus=?,MTA_DisconnectReason=?,MTA_DisconnectInitiatedBy=?,MTA_LifecycleUpdatedAt=NOW() WHERE MTA_ID=? AND MTA_OnboardingType='coexistence'");
        return $sql->execute([$status,$operacional,$reason ?: null,$initiated ?: null,(int)$id]);
    }

    public function salvar($dados)
    {
        $sql = $this->db->prepare("

            INSERT INTO meta_contas
            (

                CLI_ID,
                MTA_Nome,
                MTA_PhoneNumberId,
                MTA_WabaId,
                MTA_Token,
                MTA_UrlBase,
                MTA_NumeroTelefone,
                MTA_WebhookVerifyToken,
                MTA_AutoRespostaAtiva,
                MTA_AutoRespostaTexto,
                MTA_AutoRespostaIntervaloMinutos,
                MTA_Status,
                MTA_Ativo

            )

            VALUES
            (

                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'desconectado', 'S'

            )

        ");





        return $sql->execute([

            $dados['cliente'],

            $dados['nome'],

            $dados['phone_number_id'],

            $dados['waba_id'],

            $dados['token'],

            $dados['url_base'],

            $dados['numero'],

            $dados['webhook_verify_token'],

            $dados['auto_resposta_ativa'],

            $dados['auto_resposta_texto'],

            $dados['auto_resposta_intervalo_minutos']

        ]);
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM meta_contas

            WHERE MTA_ID = ?

            LIMIT 1

        ");

        $sql->execute([
            $id
        ]);

        return $sql->fetch(
            PDO::FETCH_ASSOC
        );
    }


    public function buscarPorIdAdmin($id)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM meta_contas
            WHERE MTA_ID = ?
            LIMIT 1
        ");

        $sql->execute([
            (int) $id
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorCliente($id, $clienteId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM meta_contas
            WHERE MTA_ID = ?
            AND CLI_ID = ?
            AND MTA_Ativo = 'S'
            LIMIT 1
        "
        );

        $sql->execute([
            $id,
            $clienteId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function contarAtivasPorCliente(
        $clienteId,
        $ignorarContaId = null
    )
    {
        $sqlExtra = '';
        $parametros = [
            $clienteId
        ];

        if(!empty($ignorarContaId)){

            $sqlExtra =
                " AND MTA_ID <> ? ";

            $parametros[] =
                $ignorarContaId;
        }

        $sql = $this->db->prepare("

            SELECT COUNT(*) AS total

            FROM meta_contas

            WHERE CLI_ID = ?
            AND MTA_Ativo = 'S'
            {$sqlExtra}

        ");

        $sql->execute(
            $parametros
        );

        return (int) $sql->fetchColumn();
    }

    public function temContaDesconectadaPorCliente($clienteId)
    {
        $sql = $this->db->prepare("SELECT 1 FROM meta_contas WHERE CLI_ID=? AND MTA_Ativo='S' AND MTA_Status='desconectado' LIMIT 1");
        $sql->execute([(int) $clienteId]);
        return (bool) $sql->fetchColumn();
    }

    public function avaliarLimiteNumerosPorCliente(
        $clienteId,
        $ignorarContaId = null,
        $preTrialElegivel = false
    )
    {
        $sql = $this->db->prepare("

            SELECT
                c.CLI_ID,
                c.CLI_Plano_DR,
                p.PLA_LimiteNumeros

            FROM clientes c

            LEFT JOIN planos p
            ON p.PLA_ID = c.CLI_Plano_DR
            AND p.PLA_Ativo = 'S'

            WHERE c.CLI_ID = ?

            LIMIT 1

        ");

        $sql->execute([
            $clienteId
        ]);

        $cliente =
            $sql->fetch(
                PDO::FETCH_ASSOC
            );

        $utilizados =
            $this->contarAtivasPorCliente(
                $clienteId,
                $ignorarContaId
            );

        $mensagemLimite =
            'Você atingiu o limite de números do seu plano. Faça upgrade para conectar mais números.';

        if($preTrialElegivel && $utilizados === 0){
            return [
                'permitido' => true,
                'sem_plano' => empty($cliente['CLI_Plano_DR']),
                'pre_trial_primeiro_numero' => true,
                'utilizados' => 0,
                'limite' => 1,
                'disponiveis' => 1,
                'mensagem' => null
            ];
        }

        if(
            !$cliente
            ||
            empty($cliente['CLI_Plano_DR'])
            ||
            empty($cliente['PLA_LimiteNumeros'])
        ){

            return [
                'permitido' => false,
                'sem_plano' => true,
                'utilizados' => $utilizados,
                'limite' => 0,
                'disponiveis' => 0,
                'mensagem' => 'Escolha um plano para conectar seu número WhatsApp.'
            ];
        }

        $limite =
            (int) $cliente['PLA_LimiteNumeros'];

        $disponiveis =
            max(
                0,
                $limite - $utilizados
            );

        return [
            'permitido' => $utilizados < $limite,
            'sem_plano' => false,
            'utilizados' => $utilizados,
            'limite' => $limite,
            'disponiveis' => $disponiveis,
            'mensagem' => $utilizados < $limite
                ? null
                : $mensagemLimite
        ];
    }

    public function validarLimiteNumerosPlano(
        $clienteId,
        $limitePlano
    )
    {
        $utilizados =
            $this->contarAtivasPorCliente(
                $clienteId
            );

        $limitePlano =
            (int) $limitePlano;

        $permitido =
            $utilizados <= $limitePlano;

        return [
            'permitido' => $permitido,
            'utilizados' => $utilizados,
            'limite' => $limitePlano,
            'mensagem' => $permitido
                ? null
                : sprintf(
                    'Para migrar para este plano, reduza a quantidade de números conectados para no máximo %d. Atualmente sua conta possui %d números conectados.',
                    $limitePlano,
                    $utilizados
                )
        ];
    }




    public function inativar($id)
    {
        $sql = $this->db->prepare("

            UPDATE meta_contas

            SET MTA_Ativo = 'N'

            WHERE MTA_ID = ?

        ");

        return $sql->execute([$id]);
    }

    public function atualizar($id, $dados)
    {
        $camposToken = '';
        $params = [
            $dados['cliente'],
            $dados['nome'],
            $dados['phone_number_id'],
            $dados['waba_id']
        ];

        if(trim((string) ($dados['token'] ?? '')) !== ''){
            $camposToken = 'MTA_Token = ?, ';
            $params[] = $dados['token'];
        }

        $params = array_merge($params, [
            $dados['url_base'],
            $dados['numero'],
            $dados['webhook_verify_token'],
            $dados['auto_resposta_ativa'],
            $dados['auto_resposta_texto'],
            $dados['auto_resposta_intervalo_minutos'],
            $id
        ]);

        $sql = $this->db->prepare("
            UPDATE meta_contas SET
                CLI_ID = ?,
                MTA_Nome = ?,
                MTA_PhoneNumberId = ?,
                MTA_WabaId = ?,
                {$camposToken}
                MTA_UrlBase = ?,
                MTA_NumeroTelefone = ?,
                MTA_WebhookVerifyToken = ?,
                MTA_AutoRespostaAtiva = ?,
                MTA_AutoRespostaTexto = ?,
                MTA_AutoRespostaIntervaloMinutos = ?
            WHERE MTA_ID = ?
        ");

        return $sql->execute($params);
    }


    public function atualizarAutoRespostaPorCliente($id, $clienteId, $dados)
    {
        $sql = $this->db->prepare("

            UPDATE meta_contas SET

                MTA_AutoRespostaAtiva = ?,

                MTA_AutoRespostaTexto = ?,

                MTA_AutoRespostaIntervaloMinutos = ?

            WHERE MTA_ID = ?
            AND CLI_ID = ?
            AND MTA_Ativo = 'S'

        ");

        $sql->execute([
            $dados['auto_resposta_ativa'],
            $dados['auto_resposta_texto'],
            $dados['auto_resposta_intervalo_minutos'],
            $id,
            $clienteId
        ]);

        return $sql->rowCount() > 0;
    }

    public function buscarPorClienteWabaPhone($clienteId, $wabaId, $phoneNumberId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM meta_contas
            WHERE CLI_ID = ?
            AND MTA_WabaId = ?
            AND MTA_PhoneNumberId = ?
            LIMIT 1
        ");

        $sql->execute([
            $clienteId,
            $wabaId,
            $phoneNumberId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function salvarOuAtualizarEmbeddedSignup(array $dados)
    {
        $existente = $this->buscarPorClienteWabaPhone(
            $dados['cliente'],
            $dados['waba_id'],
            $dados['phone_number_id']
        );

        if($existente){
            return $this->atualizarEmbeddedSignup((int) $existente['MTA_ID'], $dados)
                ? (int) $existente['MTA_ID']
                : false;
        }

        return $this->salvarEmbeddedSignup($dados);
    }

    public function salvarOuAtualizarEmbeddedSignupComBloqueio(array $dados, callable $autorizar)
    {
        $this->db->beginTransaction();

        try{
            $bloqueio = $this->db->prepare("
                SELECT CLI_ID
                FROM clientes
                WHERE CLI_ID = ?
                FOR UPDATE
            ");
            $bloqueio->execute([(int) $dados['cliente']]);

            if(!$bloqueio->fetchColumn()){
                throw new \RuntimeException('Cliente não encontrado para conectar o número.');
            }

            $autorizar();
            $contaId = $this->salvarOuAtualizarEmbeddedSignup($dados);
            $this->db->commit();

            return $contaId;
        }catch(\Throwable $e){
            if($this->db->inTransaction()){
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function salvarEmbeddedSignup(array $dados)
    {
        $colunas = [
            'CLI_ID',
            'MTA_Nome',
            'MTA_PhoneNumberId',
            'MTA_WabaId',
            'MTA_Token',
            'MTA_UrlBase',
            'MTA_NumeroTelefone',
            'MTA_Status',
            'MTA_Ativo'
        ];

        $valores = [
            $dados['cliente'],
            $dados['nome'],
            $dados['phone_number_id'],
            $dados['waba_id'],
            $dados['token'],
            $dados['url_base'],
            $dados['numero'],
            $dados['status'] ?? 'autorizada',
            'S'
        ];

        if($this->colunaWebhookVerifyTokenExiste()){
            $colunas[] = 'MTA_WebhookVerifyToken';
            $valores[] = $dados['webhook_verify_token'] ?? '';
        }

        if($this->colunasAutoRespostaExistem()){
            $colunas[] = 'MTA_AutoRespostaAtiva';
            $colunas[] = 'MTA_AutoRespostaTexto';
            $colunas[] = 'MTA_AutoRespostaIntervaloMinutos';
            $valores[] = 'N';
            $valores[] = '';
            $valores[] = 1440;
        }

        if($this->colunaExiste('MTA_BusinessId')){
            $colunas[] = 'MTA_BusinessId';
            $valores[] = $dados['business_id'] ?? null;
        }

        if($this->colunaExiste('MTA_DisplayName')){
            $colunas[] = 'MTA_DisplayName';
            $valores[] = $dados['display_name'] ?? null;
        }

        foreach([
            'MTA_QualityRating' => 'quality_rating',
            'MTA_CodeVerificationStatus' => 'code_verification_status',
            'MTA_NameStatus' => 'name_status',
            'MTA_OperationalStatus' => 'operational_status',
            'MTA_MessagingLimit' => 'messaging_limit',
            'MTA_OnboardingType' => 'onboarding_type',
            'MTA_PlatformType' => 'platform_type'
        ] as $coluna => $chave){
            if($this->colunaExiste($coluna)){
                $colunas[] = $coluna;
                $valores[] = $dados[$chave] ?? null;
            }
        }

        $placeholders = implode(', ', array_fill(0, count($colunas), '?'));

        $sql = $this->db->prepare("
            INSERT INTO meta_contas
            (" . implode(', ', $colunas) . ")
            VALUES ({$placeholders})
        ");

        $sql->execute($valores);

        return (int) $this->db->lastInsertId();
    }

    private function atualizarEmbeddedSignup($id, array $dados)
    {
        $sets = [
            'CLI_ID = ?',
            'MTA_Nome = ?',
            'MTA_PhoneNumberId = ?',
            'MTA_WabaId = ?',
            'MTA_Token = ?',
            'MTA_UrlBase = ?',
            'MTA_NumeroTelefone = ?',
            'MTA_Status = ?',
            'MTA_Ativo = ?'
        ];

        $valores = [
            $dados['cliente'],
            $dados['nome'],
            $dados['phone_number_id'],
            $dados['waba_id'],
            $dados['token'],
            $dados['url_base'],
            $dados['numero'],
            $dados['status'] ?? 'autorizada',
            'S'
        ];

        if($this->colunaWebhookVerifyTokenExiste()){
            $sets[] = 'MTA_WebhookVerifyToken = ?';
            $valores[] = $dados['webhook_verify_token'] ?? '';
        }

        if($this->colunaExiste('MTA_BusinessId')){
            $sets[] = 'MTA_BusinessId = ?';
            $valores[] = $dados['business_id'] ?? null;
        }

        if($this->colunaExiste('MTA_DisplayName')){
            $sets[] = 'MTA_DisplayName = ?';
            $valores[] = $dados['display_name'] ?? null;
        }

        foreach([
            'MTA_QualityRating' => 'quality_rating',
            'MTA_CodeVerificationStatus' => 'code_verification_status',
            'MTA_NameStatus' => 'name_status',
            'MTA_OperationalStatus' => 'operational_status',
            'MTA_MessagingLimit' => 'messaging_limit',
            'MTA_OnboardingType' => 'onboarding_type',
            'MTA_PlatformType' => 'platform_type'
        ] as $coluna => $chave){
            if($this->colunaExiste($coluna)){
                $sets[] = $coluna . ' = ?';
                $valores[] = $dados[$chave] ?? null;
            }
        }

        $valores[] = $id;

        $sql = $this->db->prepare("
            UPDATE meta_contas
            SET " . implode(', ', $sets) . "
            WHERE MTA_ID = ?
        ");

        return $sql->execute($valores);
    }



    public function atualizarStatusOperacionalEmbeddedSignup($id, $clienteId, array $dados)
    {
        return $this->atualizarEspelhoMeta($id, $clienteId, $dados, $dados['status'] ?? 'requer_acao');
    }

    public function atualizarEspelhoMeta($id, $clienteId, array $dados, $statusInterno = null)
    {
        $sets = [];
        $valores = [];

        if($statusInterno !== null){
            $sets[] = 'MTA_Status = ?';
            $valores[] = $statusInterno;
        }

        foreach([
            'MTA_QualityRating' => 'quality_rating',
            'MTA_CodeVerificationStatus' => 'code_verification_status',
            'MTA_NameStatus' => 'name_status',
            'MTA_OperationalStatus' => 'operational_status',
            'MTA_MessagingLimit' => 'messaging_limit',
            'MTA_NumeroTelefone' => 'numero',
            'MTA_DisplayName' => 'display_name',
            'MTA_PlatformType' => 'platform_type'
        ] as $coluna => $chave){
            if(!($this->colunaExiste($coluna) || in_array($coluna, ['MTA_NumeroTelefone'], true))){
                continue;
            }

            if(!array_key_exists($chave, $dados)){
                continue;
            }

            $valor = $dados[$chave];
            if($valor === null || $valor === ''){
                continue;
            }

            $sets[] = $coluna . ' = ?';
            $valores[] = $valor;
        }

        $sets[] = 'MTA_UltimaVerificacao = NOW()';

        $valores[] = $id;
        $valores[] = $clienteId;

        $sql = $this->db->prepare("
            UPDATE meta_contas
            SET " . implode(', ', $sets) . "
            WHERE MTA_ID = ?
            AND CLI_ID = ?
            AND MTA_Ativo = 'S'
        ");

        return $sql->execute($valores);
    }

    public function listarPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM meta_contas

            WHERE CLI_ID = ?
            AND MTA_Ativo = 'S'

            ORDER BY MTA_ID DESC

        ");





        $sql->execute([
            $clienteId
        ]);





        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function listarPorUsuario($usuario)
    {
        $metaIds = \Core\Auth::idsContasMetaPermitidas($usuario);
        $clienteId = (int) ($usuario['CLI_ID'] ?? ($usuario['cliente_id'] ?? 0));

        if($clienteId <= 0 || empty($metaIds)){
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($metaIds), '?'));
        $sql = $this->db->prepare("\n            SELECT *\n            FROM meta_contas\n            WHERE CLI_ID = ?\n            AND MTA_ID IN ($placeholders)\n            AND MTA_Ativo = 'S'\n            ORDER BY MTA_ID DESC\n        ");

        $sql->execute(array_merge([$clienteId], array_map('intval', $metaIds)));

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorUsuario($id, $usuario)
    {
        $metaIds = \Core\Auth::idsContasMetaPermitidas($usuario);
        $clienteId = (int) ($usuario['CLI_ID'] ?? ($usuario['cliente_id'] ?? 0));

        if($clienteId <= 0 || empty($metaIds) || !in_array((int) $id, $metaIds, true)){
            return false;
        }

        $sql = $this->db->prepare("\n            SELECT *\n            FROM meta_contas\n            WHERE MTA_ID = ?\n            AND CLI_ID = ?\n            AND MTA_Ativo = 'S'\n            LIMIT 1\n        ");

        $sql->execute([(int) $id, $clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

}
