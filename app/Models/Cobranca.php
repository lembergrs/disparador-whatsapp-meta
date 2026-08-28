<?php

namespace Models;

use Core\Database;
use PDO;
use PDOException;

class Cobranca
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function buscarPendentePorCliente($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT c.*, p.PLA_Nome
            FROM cobrancas c
            LEFT JOIN planos p ON p.PLA_ID = c.PLA_ID
            WHERE c.CLI_ID = ?
            AND c.COB_Status = 'pendente'
            ORDER BY c.COB_ID DESC
            LIMIT 1
        ");

        $sql->execute([$clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarObrigacaoAbertaPorAssinatura($clienteId, $assinaturaId)
    {
        $vencimento = $this->colunaExiste('cobrancas', 'COB_DataVencimentoEfetivo')
            ? 'COALESCE(c.COB_DataVencimentoEfetivo, c.COB_DataVencimento)'
            : 'c.COB_DataVencimento';
        $sql = $this->db->prepare("SELECT c.*, p.PLA_Nome, {$vencimento} AS COB_VencimentoFinanceiro FROM cobrancas c LEFT JOIN planos p ON p.PLA_ID = c.PLA_ID WHERE c.CLI_ID = ? AND c.ASS_ID = ? AND c.COB_Status IN ('pendente','vencido') ORDER BY {$vencimento} ASC, c.COB_ID ASC LIMIT 1");
        $sql->execute([(int) $clienteId, (int) $assinaturaId]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listarPendentesPorCliente($clienteId)
    {
        $sql = $this->db->prepare("SELECT * FROM cobrancas WHERE CLI_ID = ? AND COB_Status = 'pendente' ORDER BY COB_ID");
        $sql->execute([(int) $clienteId]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarAnterioresDoCliente($clienteId, $cobrancaId = null)
    {
        $filtroId = $cobrancaId === null ? '' : ' AND COB_ID < ?';
        $sql = $this->db->prepare("SELECT COUNT(*) FROM cobrancas WHERE CLI_ID = ?{$filtroId} AND COB_Status <> 'cancelado'");
        $params = [(int) $clienteId];
        if($cobrancaId !== null){ $params[] = (int) $cobrancaId; }
        $sql->execute($params);
        return (int) $sql->fetchColumn();
    }

    public function contarPagasPorCliente($clienteId)
    {
        $sql = $this->db->prepare("SELECT COUNT(*) FROM cobrancas WHERE CLI_ID = ? AND COB_Status = 'pago'");
        $sql->execute([(int) $clienteId]);
        return (int) $sql->fetchColumn();
    }

    public function registrarComposicaoDesconto($id, array $dados)
    {
        $mapa = [
            'COB_Valor'=>'valor',
            'COB_ValorBaseCentavos'=>'valor_base_centavos',
            'COB_DescontoInicialCentavos'=>'desconto_inicial_centavos',
            'COB_DescontoIndicacaoCentavos'=>'desconto_indicacao_centavos',
            'COB_AdicionaisCentavos'=>'adicionais_centavos',
            'COB_Ciclo'=>'ciclo'
        ];
        $sets = [];
        $params = [':id'=>(int) $id];
        foreach($mapa as $coluna=>$chave){
            if($this->colunaExiste('cobrancas', $coluna) && array_key_exists($chave, $dados)){
                $sets[] = $coluna . '=:' . $chave;
                $params[':' . $chave] = $dados[$chave];
            }
        }
        if(!$sets){ return false; }
        $sql = $this->db->prepare('UPDATE cobrancas SET ' . implode(',', $sets) . ' WHERE COB_ID=:id');
        return $sql->execute($params);
    }


    public function listarPorCliente($clienteId)
    {
        $sql = $this->db->prepare("\n            SELECT c.*, p.PLA_Nome\n            FROM cobrancas c\n            LEFT JOIN planos p ON p.PLA_ID = c.PLA_ID\n            WHERE c.CLI_ID = ?\n            ORDER BY c.COB_ID DESC\n        ");

        $sql->execute([$clienteId]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }



    public function contarPorCliente($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*)
            FROM cobrancas
            WHERE CLI_ID = ?
        ");

        $sql->execute([$clienteId]);

        return (int) $sql->fetchColumn();
    }

    public function listarPorClientePaginado($clienteId, $limit, $offset)
    {
        $limit = max(1, min(50, (int) $limit));
        $offset = max(0, (int) $offset);

        $sql = $this->db->prepare("
            SELECT c.*, p.PLA_Nome
            FROM cobrancas c
            LEFT JOIN planos p ON p.PLA_ID = c.PLA_ID
            WHERE c.CLI_ID = :cliente
            ORDER BY c.COB_ID DESC
            LIMIT {$limit} OFFSET {$offset}
        ");

        $sql->bindValue(':cliente', $clienteId, PDO::PARAM_INT);
        $sql->execute();

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar($dados)
    {
        $campos = [
            'CLI_ID',
            'PLA_ID',
            'COB_Valor',
            'COB_Status',
            'COB_Forma',
            'COB_DataVencimento'
        ];
        $valores = [
            ':cliente',
            ':plano',
            ':valor',
            "'pendente'",
            "'bolepix'",
            ':vencimento'
        ];
        $params = [
            ':cliente' => $dados['cliente'],
            ':plano' => $dados['plano'],
            ':valor' => $dados['valor'],
            ':vencimento' => $dados['vencimento']
        ];

        if($this->colunaExiste('cobrancas', 'COB_Tipo')){
            $campos[] = 'COB_Tipo';
            $valores[] = ':tipo';
            $params[':tipo'] = $dados['tipo'] ?? 'mensalidade';
        }

        $camposOpcionais = [
            'ASS_ID' => 'assinatura',
            'COB_DataVencimentoEfetivo' => 'vencimento_efetivo',
            'COB_Provider' => 'provider',
            'COB_ProviderCustomerId' => 'provider_customer_id',
            'COB_ProviderPaymentId' => 'provider_payment_id',
            'COB_ProviderStatus' => 'provider_status',
            'COB_ProviderPayload' => 'provider_payload',
            'COB_LinkPagamento' => 'link_pagamento',
            'COB_PixCopiaCola' => 'pix_copia_cola',
            'COB_QrCode' => 'qr_code',
            'COB_LinhaDigitavel' => 'linha_digitavel'
        ];

        foreach($camposOpcionais as $coluna => $chave){
            if($this->colunaExiste('cobrancas', $coluna) && array_key_exists($chave, $dados)){
                $param = ':' . $chave;
                $campos[] = $coluna;
                $valores[] = $param;
                $params[$param] = $dados[$chave];
            }
        }

        if($this->colunaExiste('cobrancas', 'COB_DataSincronizacaoProvider') && !empty($dados['sincronizado_provider'])){
            $campos[] = 'COB_DataSincronizacaoProvider';
            $valores[] = 'NOW()';
        }

        $sql = $this->db->prepare("
            INSERT INTO cobrancas (
                " . implode(', ', $campos) . "
            ) VALUES (
                " . implode(', ', $valores) . "
            )
        ");

        $sql->execute($params);

        return $this->db->lastInsertId();
    }

    public function marcarPago($id)
    {
        $sql = $this->db->prepare("
            UPDATE cobrancas
            SET
                COB_Status = 'pago',
                COB_DataPagamento = NOW()
            WHERE COB_ID = ?
            AND COB_Status IN ('pendente','vencido')
        ");

        return $sql->execute([$id]);
    }

    public function registrarPagamentoManual($id, array $dados)
    {
        if(!$this->colunaExiste('cobrancas', 'COB_ProviderPayload')){ return true; }
        $payload = json_encode([
            'source'=>'manual', 'actual_paid_centavos'=>(int) $dados['valor_pago_centavos'],
            'referral_discount_decision'=>$dados['decisao_indicacao'], 'note'=>$dados['motivo'] ?: null,
            'administrator_id'=>$dados['usuario_id'], 'expected_centavos'=>(int) $dados['valor_esperado_centavos'],
            'amount_divergent'=>(bool) $dados['valor_divergente']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sets = ['COB_ProviderPayload = :payload'];
        if($this->colunaExiste('cobrancas', 'COB_ProviderStatus')){ $sets[] = "COB_ProviderStatus = 'manual_confirmado'"; }
        $sql = $this->db->prepare('UPDATE cobrancas SET ' . implode(', ', $sets) . ' WHERE COB_ID = :id');
        return $sql->execute([':id'=>(int) $id, ':payload'=>$payload]);
    }

    public function listar()
    {
        $sql = $this->db->query("
            SELECT
                c.*,
                cli.CLI_Nome,
                p.PLA_Nome
            FROM cobrancas c
            LEFT JOIN clientes cli
                ON cli.CLI_ID = c.CLI_ID
            LEFT JOIN planos p
                ON p.PLA_ID = c.PLA_ID
            ORDER BY c.COB_ID DESC
        ");

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM cobrancas
            WHERE COB_ID = ?
        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarParaAtualizacao($id)
    {
        $sql = $this->db->prepare('SELECT * FROM cobrancas WHERE COB_ID = ? FOR UPDATE');
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function cancelar($id)
    {
        $sql = $this->db->prepare("
            UPDATE cobrancas
            SET COB_Status = 'cancelado'
            WHERE COB_ID = ?
        ");

        return $sql->execute([$id]);
    }

    public function cancelarPendentesPorCliente($clienteId)
    {
        $sql = $this->db->prepare("UPDATE cobrancas SET COB_Status = 'cancelado' WHERE CLI_ID = ? AND COB_Status = 'pendente'");
        $sql->execute([$clienteId]);
        return $sql->rowCount();
    }

    public function buscarUltimaPorCliente($clienteId)
    {
        $sql = $this->db->prepare("SELECT * FROM cobrancas WHERE CLI_ID = ? ORDER BY COB_ID DESC LIMIT 1");
        $sql->execute([$clienteId]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listarPendentesVencidas()
    {
        $vencimento = $this->colunaExiste('cobrancas', 'COB_DataVencimentoEfetivo')
            ? 'COALESCE(COB_DataVencimentoEfetivo, COB_DataVencimento)'
            : 'COB_DataVencimento';
        $sql = $this->db->query("SELECT * FROM cobrancas WHERE COB_Status = 'pendente' AND {$vencimento} < CURDATE() ORDER BY {$vencimento} ASC, COB_ID ASC");
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarObrigacaoVencidaPorAssinatura($clienteId, $assinaturaId, $dataReferencia)
    {
        $vencimento = $this->colunaExiste('cobrancas', 'COB_DataVencimentoEfetivo')
            ? 'COALESCE(COB_DataVencimentoEfetivo, COB_DataVencimento)'
            : 'COB_DataVencimento';
        $sql = $this->db->prepare("SELECT *, {$vencimento} AS COB_VencimentoFinanceiro FROM cobrancas WHERE CLI_ID = ? AND ASS_ID = ? AND COB_Status IN ('pendente','vencido') AND {$vencimento} < ? ORDER BY {$vencimento} ASC, COB_ID ASC LIMIT 1");
        $sql->execute([(int) $clienteId, (int) $assinaturaId, $dataReferencia]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function definirVencimentoEfetivo($id, $data)
    {
        if(!$this->colunaExiste('cobrancas', 'COB_DataVencimentoEfetivo')){
            return false;
        }
        $sql = $this->db->prepare('UPDATE cobrancas SET COB_DataVencimentoEfetivo = ? WHERE COB_ID = ?');
        return $sql->execute([$data, (int) $id]);
    }

    public function existeRecorrente($clienteId, $planoId, $vencimento, $tipo = 'mensalidade', $assinaturaId = null)
    {
        return (bool) $this->buscarRecorrente($clienteId, $planoId, $vencimento, $tipo, $assinaturaId);
    }

    public function buscarRecorrente($clienteId, $planoId, $vencimento, $tipo = 'mensalidade', $assinaturaId = null)
    {
        $params = [$clienteId, $planoId, $vencimento];
        $filtro = '';
        if($this->colunaExiste('cobrancas', 'COB_Tipo')){ $filtro = ' AND COB_Tipo = ?'; $params[] = $tipo; }
        if($assinaturaId !== null && $this->colunaExiste('cobrancas', 'ASS_ID')){ $filtro .= ' AND ASS_ID = ?'; $params[] = $assinaturaId; }
        $sql = $this->db->prepare("SELECT * FROM cobrancas WHERE CLI_ID = ? AND PLA_ID = ? AND COB_DataVencimento = ? AND COB_Status <> 'cancelado' {$filtro} ORDER BY COB_ID DESC LIMIT 1");
        $sql->execute($params);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorCompetencia($assinaturaId, $vencimento, $tipo = 'mensalidade')
    {
        $sql = $this->db->prepare("SELECT * FROM cobrancas WHERE ASS_ID = ? AND COB_DataVencimento = ? AND COB_Tipo = ? LIMIT 1");
        $sql->execute([$assinaturaId, $vencimento, $tipo]);
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function criarRecorrenteIdempotente(array $dados)
    {
        try{
            return ['id' => (int) $this->criar($dados), 'criada' => true];
        }catch(PDOException $e){
            if((string) $e->getCode() !== '23000'){
                throw $e;
            }

            $existente = $this->buscarPorCompetencia(
                $dados['assinatura'],
                $dados['vencimento'],
                $dados['tipo'] ?? 'mensalidade'
            );
            if(!$existente){
                throw $e;
            }
            return ['id' => (int) $existente['COB_ID'], 'criada' => false];
        }
    }

    public function prepararReprocessamento($id, $tentativa)
    {
        $sets = ["COB_Status = 'pendente'"];
        foreach(['COB_ProviderPaymentId','COB_LinkPagamento','COB_PixCopiaCola','COB_QrCode','COB_LinhaDigitavel'] as $coluna){
            if($this->colunaExiste('cobrancas', $coluna)){ $sets[] = $coluna . ' = NULL'; }
        }
        if($this->colunaExiste('cobrancas', 'COB_ProviderStatus')){
            $status = (int) $tentativa <= 1 ? 'reprocessamento_base' : 'reprocessamento_tentativa_' . (int) $tentativa;
            $sets[] = "COB_ProviderStatus = " . $this->db->quote($status);
        }
        $sql = $this->db->prepare('UPDATE cobrancas SET ' . implode(', ', $sets) . ' WHERE COB_ID = ?');
        return $sql->execute([$id]);
    }

    public function comLockIntegracao($id, callable $operacao)
    {
        $nome = 'financeiro_cobranca_' . (int) $id;
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 30)');
        $stmt->execute([$nome]);
        if((int) $stmt->fetchColumn() !== 1){
            throw new \RuntimeException('Não foi possível obter o bloqueio da cobrança.');
        }

        try{
            return $operacao();
        }finally{
            $liberar = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $liberar->execute([$nome]);
        }
    }

    public function atualizarIntegracaoProvider($id, $dados)
    {
        $sets = [];
        $params = [':id' => $id];
        $mapa = [
            'COB_Provider' => 'provider',
            'COB_ProviderCustomerId' => 'provider_customer_id',
            'COB_ProviderPaymentId' => 'provider_payment_id',
            'COB_ProviderStatus' => 'provider_status',
            'COB_ProviderPayload' => 'provider_payload',
            'COB_LinkPagamento' => 'link_pagamento',
            'COB_PixCopiaCola' => 'pix_copia_cola',
            'COB_QrCode' => 'qr_code',
            'COB_LinhaDigitavel' => 'linha_digitavel',
            'COB_Status' => 'status',
            'COB_DataPagamento' => 'data_pagamento'
        ];

        foreach($mapa as $coluna => $chave){
            if($this->colunaExiste('cobrancas', $coluna) && array_key_exists($chave, $dados)){
                $param = ':' . $chave;
                $sets[] = $coluna . ' = ' . $param;
                $params[$param] = $dados[$chave];
            }
        }

        if($this->colunaExiste('cobrancas', 'COB_DataSincronizacaoProvider')){
            $sets[] = 'COB_DataSincronizacaoProvider = NOW()';
        }

        if(empty($sets)){
            return false;
        }

        $sql = $this->db->prepare("\n            UPDATE cobrancas\n            SET " . implode(', ', $sets) . "\n            WHERE COB_ID = :id\n        ");

        return $sql->execute($params);
    }


    public function vincularAssinatura($cobrancaId, $assinaturaId)
    {
        if(!$this->colunaExiste('cobrancas', 'ASS_ID')){
            return false;
        }

        $sql = $this->db->prepare("\n            UPDATE cobrancas\n            SET ASS_ID = ?\n            WHERE COB_ID = ?\n        ");

        return $sql->execute([$assinaturaId, $cobrancaId]);
    }


    public function buscarPorProviderPaymentId($provider, $providerPaymentId)
    {
        if(!$this->colunaExiste('cobrancas', 'COB_Provider') || !$this->colunaExiste('cobrancas', 'COB_ProviderPaymentId')){
            return false;
        }

        $sql = $this->db->prepare("\n            SELECT *\n            FROM cobrancas\n            WHERE COB_Provider = ?\n            AND COB_ProviderPaymentId = ?\n            ORDER BY COB_ID DESC\n            LIMIT 1\n        ");

        $sql->execute([$provider, $providerPaymentId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarEventoProvider($cobrancaId, $provider, $providerEventId, $evento, $status, $payload)
    {
        if(!$this->tabelaExiste('cobranca_eventos')){
            return false;
        }

        $campos = [];
        $valores = [];
        $params = [];
        $mapa = [
            'COB_ID' => $cobrancaId,
            'CEV_Provider' => $provider,
            'CEV_ProviderEventId' => $providerEventId,
            'CEV_Evento' => $evento,
            'CEV_Status' => $status,
            'CEV_Payload' => $payload
        ];

        foreach($mapa as $coluna => $valor){
            if($this->colunaExiste('cobranca_eventos', $coluna)){
                $param = ':' . strtolower($coluna);
                $campos[] = $coluna;
                $valores[] = $param;
                $params[$param] = $valor;
            }
        }

        foreach(['CEV_DataCadastro', 'CEV_DataRecebimento', 'CEV_DataEvento'] as $colunaData){
            if($this->colunaExiste('cobranca_eventos', $colunaData)){
                $campos[] = $colunaData;
                $valores[] = 'NOW()';
                break;
            }
        }

        if(empty($campos)){
            return false;
        }

        $sql = $this->db->prepare("\n            INSERT INTO cobranca_eventos (" . implode(', ', $campos) . ")\n            VALUES (" . implode(', ', $valores) . ")\n        ");

        try{
            return $sql->execute($params);
        }catch(PDOException $e){
            if((string) $e->getCode() === '23000'){
                return 'duplicado';
            }
            throw $e;
        }
    }

    private function eventoProviderExiste($provider, $providerEventId)
    {
        if(!$this->colunaExiste('cobranca_eventos', 'CEV_Provider') || !$this->colunaExiste('cobranca_eventos', 'CEV_ProviderEventId')){
            return false;
        }

        $sql = $this->db->prepare("\n            SELECT 1\n            FROM cobranca_eventos\n            WHERE CEV_Provider = ?\n            AND CEV_ProviderEventId = ?\n            LIMIT 1\n        ");

        $sql->execute([$provider, $providerEventId]);

        return (bool) $sql->fetchColumn();
    }

    private function tabelaExiste($tabela)
    {
        static $cache = [];

        if(array_key_exists($tabela, $cache)){
            return $cache[$tabela];
        }

        $sql = $this->db->prepare("\n            SHOW TABLES LIKE ?\n        ");

        $sql->execute([$tabela]);

        $cache[$tabela] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$tabela];
    }




    private function colunaExiste($tabela, $coluna)
    {
        static $cache = [];

        $chave = $tabela . '.' . $coluna;

        if(array_key_exists($chave, $cache)){
            return $cache[$chave];
        }

        $sql = $this->db->prepare("
            SHOW COLUMNS FROM {$tabela} LIKE ?
        ");

        $sql->execute([$coluna]);

        $cache[$chave] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$chave];
    }
}
