<?php

namespace Models;

use Core\Database;
use PDO;

class NfseEmissao
{
    public const STATUS_PENDENTE_DADOS = 'pendente_dados';
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_PROCESSANDO = 'processando';
    public const STATUS_RECONCILIACAO_PENDENTE = 'reconciliacao_pendente';
    public const STATUS_EMITIDA = 'emitida';
    public const STATUS_ERRO_TEMPORARIO = 'erro_temporario';
    public const STATUS_ERRO_DEFINITIVO = 'erro_definitivo';
    public const STATUS_CANCELAMENTO_PENDENTE = 'cancelamento_pendente';
    public const STATUS_CANCELADA = 'cancelada';

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function statusPermitidos()
    {
        return [
            self::STATUS_PENDENTE_DADOS,
            self::STATUS_PENDENTE,
            self::STATUS_PROCESSANDO,
            self::STATUS_RECONCILIACAO_PENDENTE,
            self::STATUS_EMITIDA,
            self::STATUS_ERRO_TEMPORARIO,
            self::STATUS_ERRO_DEFINITIVO,
            self::STATUS_CANCELAMENTO_PENDENTE,
            self::STATUS_CANCELADA
        ];
    }

    public static function chaveIdempotencia($cobrancaId)
    {
        return 'nfse:cobranca:' . (int) $cobrancaId;
    }

    public function buscarPorCobranca($cobrancaId)
    {
        $sql = $this->db->prepare("SELECT * FROM nfse_emissoes WHERE COB_ID = ? LIMIT 1");
        $sql->execute([(int) $cobrancaId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorClienteECobranca($clienteId, $cobrancaId)
    {
        $sql = $this->db->prepare("SELECT * FROM nfse_emissoes WHERE CLI_ID = ? AND COB_ID = ? LIMIT 1");
        $sql->execute([(int) $clienteId, (int) $cobrancaId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function criarOuBuscarPorCobranca(array $cobranca, array $opcoes = [])
    {
        $cobrancaId = (int) ($cobranca['COB_ID'] ?? 0);
        $clienteId = (int) ($cobranca['CLI_ID'] ?? 0);

        if($cobrancaId <= 0 || $clienteId <= 0){
            throw new \InvalidArgumentException('Cobrança inválida para reserva de NFS-e.');
        }

        $existente = $this->buscarPorCobranca($cobrancaId);

        if($existente){
            return $existente;
        }

        $idempotencyKey = self::chaveIdempotencia($cobrancaId);
        $status = in_array(($opcoes['status'] ?? self::STATUS_PENDENTE_DADOS), self::statusPermitidos(), true)
            ? $opcoes['status']
            : self::STATUS_PENDENTE_DADOS;

        $sql = $this->db->prepare("
            INSERT INTO nfse_emissoes (
                CLI_ID, COB_ID, NFE_ReferenciaPagamento, NFE_Status, NFE_IdempotencyKey,
                NFE_Competencia, NFE_ValorFiscal, NFE_DescricaoServico, NFE_Serie, NFE_DataReserva
            ) VALUES (
                :cliente, :cobranca, :referencia, :status, :idempotency,
                :competencia, :valor, :descricao, :serie, NOW()
            )
        ");

        try{
            $sql->execute([
                ':cliente' => $clienteId,
                ':cobranca' => $cobrancaId,
                ':referencia' => $cobranca['COB_ProviderPaymentId'] ?? null,
                ':status' => $status,
                ':idempotency' => $idempotencyKey,
                ':competencia' => $opcoes['competencia'] ?? date('Y-m-d'),
                ':valor' => $opcoes['valor'] ?? ($cobranca['COB_Valor'] ?? 0),
                ':descricao' => $opcoes['descricao'] ?? 'Mensalidade Disparador.net',
                ':serie' => $opcoes['serie'] ?? (defined('NFSE_DPS_SERIE') ? NFSE_DPS_SERIE : '900')
            ]);
        }catch(\PDOException $e){
            $existente = $this->buscarPorCobranca($cobrancaId);

            if($existente){
                return $existente;
            }

            throw $e;
        }

        return $this->buscarPorCobranca($cobrancaId);
    }

    public function atribuirNumDps($nfseId, $numDps)
    {
        $sql = $this->db->prepare("
            UPDATE nfse_emissoes
            SET NFE_NumDps = ?, NFE_DataAtualizacao = NOW()
            WHERE NFE_ID = ?
            AND NFE_NumDps IS NULL
        ");

        $sql->execute([(string) $numDps, (int) $nfseId]);

        return $sql->rowCount() === 1;
    }

    public function atualizarStatus($nfseId, $status, array $erro = [])
    {
        if(!in_array($status, self::statusPermitidos(), true)){
            throw new \InvalidArgumentException('Status de NFS-e inválido.');
        }

        $sql = $this->db->prepare("
            UPDATE nfse_emissoes
            SET NFE_Status = :status,
                NFE_UltimoErroTipo = :erro_tipo,
                NFE_UltimoErroCodigo = :erro_codigo,
                NFE_UltimoErroMensagem = :erro_mensagem,
                NFE_DataAtualizacao = NOW()
            WHERE NFE_ID = :id
        ");

        return $sql->execute([
            ':status' => $status,
            ':erro_tipo' => $erro['tipo'] ?? null,
            ':erro_codigo' => $erro['codigo'] ?? null,
            ':erro_mensagem' => $this->sanitizarMensagem($erro['mensagem'] ?? null),
            ':id' => (int) $nfseId
        ]);
    }

    public function sanitizarMensagem($mensagem)
    {
        if($mensagem === null){
            return null;
        }

        $mensagem = preg_replace('/(Authorization|Bearer|API_AUTH_TOKEN|senhaCert|CERT_PASSWORD|PFX|base64)[^,;\s]*/i', '$1=***', (string) $mensagem);
        $mensagem = preg_replace('/[\r\n\t]+/', ' ', $mensagem);

        return mb_substr(trim($mensagem), 0, 1000);
    }
}
