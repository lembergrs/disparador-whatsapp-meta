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

        if(
            $tentativaId <= 0 || $cobrancaId <= 0 || $clienteId <= 0
            || (int) ($cobranca['CLI_ID'] ?? 0) !== $clienteId
            || strlen($chave) !== 50
            || empty($nota['xml_path']) || empty($nota['xml_hash'])
        ){
            throw new \InvalidArgumentException('Dados inválidos para reconciliar NFS-e externa.');
        }

        $this->db->beginTransaction();
        try{
            $lock = $this->db->prepare('SELECT * FROM nfse_emissoes WHERE NFE_ID = ? FOR UPDATE');
            $lock->execute([$tentativaId]);
            $atual = $lock->fetch(PDO::FETCH_ASSOC);

            if(
                !$atual
                || (int) ($atual['COB_ID'] ?? 0) !== $cobrancaId
                || (int) ($atual['CLI_ID'] ?? 0) !== $clienteId
                || (int) ($atual['NFE_EmissaoAtiva'] ?? 0) !== 1
            ){
                throw new \RuntimeException('A tentativa fiscal não está mais ativa para reconciliação.');
            }
            if(!in_array($atual['NFE_Status'] ?? '', [NfseEmissao::STATUS_ERRO_TEMPORARIO, NfseEmissao::STATUS_ERRO_DEFINITIVO], true)){
                throw new \RuntimeException('Apenas tentativas com erro podem ser substituídas por uma emissão externa.');
            }

            $duplicada = $this->db->prepare('SELECT NFE_ID FROM nfse_emissoes WHERE NFE_ChaveAcesso = ? LIMIT 1');
            $duplicada->execute([$chave]);
            if($duplicada->fetchColumn()){
                throw new \RuntimeException('Esta chave de acesso já está cadastrada no Disparador.');
            }

            $encerrar = $this->db->prepare("
                UPDATE nfse_emissoes
                SET NFE_Status = :status,
                    NFE_EmissaoAtiva = NULL,
                    NFE_UltimoErroTipo = 'reconciliacao_externa',
                    NFE_UltimoErroCodigo = 'substituida_por_emissao_externa',
                    NFE_UltimoErroMensagem = 'Tentativa automática encerrada após confirmação de NFS-e emitida externamente.',
                    NFE_DataAtualizacao = NOW()
                WHERE NFE_ID = :id AND NFE_EmissaoAtiva = 1
            ");
            $encerrar->execute([
                ':status' => NfseEmissao::STATUS_ERRO_DEFINITIVO,
                ':id' => $tentativaId
            ]);
            if($encerrar->rowCount() !== 1){
                throw new \RuntimeException('Não foi possível encerrar a tentativa automática anterior.');
            }

            $insert = $this->db->prepare("
                INSERT INTO nfse_emissoes (
                    CLI_ID, COB_ID, NFE_ReferenciaPagamento, NFE_Status, NFE_EmissaoAtiva, NFE_IdempotencyKey,
                    NFE_PrestadorCnpj, NFE_Ambiente, NFE_NumDps, NFE_ChaveAcesso, NFE_NumeroNota, NFE_Serie,
                    NFE_Competencia, NFE_DataEmissao, NFE_ValorFiscal, NFE_DescricaoServico,
                    NFE_CodigoTributacaoNacional, NFE_DescricaoServicoSnapshot, NFE_RetornoSanitizado,
                    NFE_XmlStoragePath, NFE_XmlSha256, NFE_DataCriacao, NFE_DataAtualizacao
                ) VALUES (
                    :cliente, :cobranca, :referencia, :status, 1, :idempotency,
                    :prestador, :ambiente, :num_dps, :chave, :numero_nota, :serie,
                    :competencia, :data_emissao, :valor, :descricao,
                    :codigo_tributacao, :descricao_snapshot, :retorno,
                    :xml_path, :xml_hash, NOW(), NOW()
                )
            ");
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
                ':retorno' => json_encode([
                    'origem' => 'emissao_externa',
                    'tentativa_substituida' => $tentativaId
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':xml_path' => $nota['xml_path'],
                ':xml_hash' => $nota['xml_hash']
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
