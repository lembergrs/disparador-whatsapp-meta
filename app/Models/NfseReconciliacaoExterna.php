<?php

namespace Models;

use Core\Database;
use PDO;

class NfseReconciliacaoExterna
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function registrar(array $tentativa, array $cobranca, array $nota)
    {
        $tentativaId = (int) ($tentativa['NFE_ID'] ?? 0);
        $cobrancaId = (int) ($tentativa['COB_ID'] ?? 0);
        $clienteId = (int) ($tentativa['CLI_ID'] ?? 0);
        $chave = preg_replace('/\D/', '', (string) ($nota['chave_acesso'] ?? ''));

        if($tentativaId <= 0 || $cobrancaId <= 0 || $clienteId <= 0 || strlen($chave) !== 50){
            throw new \InvalidArgumentException('Dados inválidos para reconciliar NFS-e externa.');
        }

        $this->db->beginTransaction();
        try{
            $lock = $this->db->prepare('SELECT * FROM nfse_emissoes WHERE NFE_ID = ? FOR UPDATE');
            $lock->execute([$tentativaId]);
            $atual = $lock->fetch(PDO::FETCH_ASSOC);

            if(!$atual || (int) ($atual['COB_ID'] ?? 0) !== $cobrancaId || (int) ($atual['NFE_EmissaoAtiva'] ?? 0) !== 1){
                throw new \RuntimeException('A tentativa fiscal não está mais ativa para reconciliação.');
            }
            if(($atual['NFE_Status'] ?? '') === NfseEmissao::STATUS_EMITIDA || ($atual['NFE_Status'] ?? '') === NfseEmissao::STATUS_CANCELAMENTO_PENDENTE){
                throw new \RuntimeException('Uma NFS-e já foi emitida para esta tentativa.');
            }

            $duplicada = $this->db->prepare('SELECT NFE_ID FROM nfse_emissoes WHERE NFE_ChaveAcesso = ? LIMIT 1');
            $duplicada->execute([$chave]);
            if($duplicada->fetchColumn()){
                throw new \RuntimeException('Esta chave de acesso já está cadastrada no Disparador.');
            }

            $encerrar = $this->db->prepare("\n                UPDATE nfse_emissoes\n                SET NFE_Status = :status,\n                    NFE_EmissaoAtiva = NULL,\n                    NFE_UltimoErroTipo = 'reconciliacao_externa',\n                    NFE_UltimoErroCodigo = 'substituida_por_emissao_externa',\n                    NFE_UltimoErroMensagem = 'Tentativa automática encerrada após confirmação de NFS-e emitida externamente.',\n                    NFE_DataAtualizacao = NOW()\n                WHERE NFE_ID = :id AND NFE_EmissaoAtiva = 1\n            ");
            $encerrar->execute([':status' => NfseEmissao::STATUS_ERRO_DEFINITIVO, ':id' => $tentativaId]);
            if($encerrar->rowCount() !== 1){
                throw new \RuntimeException('Não foi possível encerrar a tentativa automática anterior.');
            }

            $insert = $this->db->prepare("\n                INSERT INTO nfse_emissoes (\n                    CLI_ID, COB_ID, NFE_ReferenciaPagamento, NFE_Status, NFE_EmissaoAtiva, NFE_IdempotencyKey,\n                    NFE_PrestadorCnpj, NFE_Ambiente, NFE_NumDps, NFE_ChaveAcesso, NFE_NumeroNota, NFE_Serie,\n                    NFE_Competencia, NFE_DataEmissao, NFE_ValorFiscal, NFE_DescricaoServico,\n                    NFE_CodigoTributacaoNacional, NFE_DescricaoServicoSnapshot, NFE_RetornoSanitizado,\n                    NFE_DataReserva, NFE_DataCriacao, NFE_DataAtualizacao\n                ) VALUES (\n                    :cliente, :cobranca, :referencia, :status, 1, :idempotency,\n                    :prestador, :ambiente, :num_dps, :chave, :numero_nota, :serie,\n                    :competencia, :data_emissao, :valor, :descricao,\n                    :codigo_tributacao, :descricao_snapshot, :retorno,\n                    NOW(), NOW(), NOW()\n                )\n            ");
            $insert->execute([
                ':cliente' => $clienteId,
                ':cobranca' => $cobrancaId,
                ':referencia' => $cobranca['COB_ProviderPaymentId'] ?? null,
                ':status' => NfseEmissao::STATUS_EMITIDA,
                ':idempotency' => NfseEmissao::chaveIdempotencia($cobrancaId),
                ':prestador' => $nota['prestador_cnpj'],
                ':ambiente' => $nota['ambiente'] ?? 'production',
                ':num_dps' => $nota['num_dps'],
                ':chave' => $chave,
                ':numero_nota' => $nota['numero_nfse'],
                ':serie' => $nota['serie'],
                ':competencia' => $nota['competencia'],
                ':data_emissao' => $nota['data_emissao'],
                ':valor' => $nota['valor'],
                ':descricao' => $nota['descricao_servico'] ?? null,
                ':codigo_tributacao' => $nota['codigo_tributacao'] ?? null,
                ':descricao_snapshot' => $nota['descricao_servico'] ?? null,
                ':retorno' => json_encode(['origem' => 'emissao_externa', 'tentativa_substituida' => $tentativaId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

            $novoId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $novoId;
        }catch(\Throwable $e){
            if($this->db->inTransaction()){
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
