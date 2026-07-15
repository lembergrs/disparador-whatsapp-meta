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

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public static function statusPermitidos()
    {
        return array_keys(self::transicoesPermitidas());
    }

    public static function transicoesPermitidas()
    {
        return [
            self::STATUS_PENDENTE_DADOS => [self::STATUS_PENDENTE, self::STATUS_ERRO_DEFINITIVO],
            self::STATUS_PENDENTE => [self::STATUS_PROCESSANDO, self::STATUS_ERRO_DEFINITIVO],
            self::STATUS_PROCESSANDO => [self::STATUS_EMITIDA, self::STATUS_RECONCILIACAO_PENDENTE, self::STATUS_ERRO_TEMPORARIO, self::STATUS_ERRO_DEFINITIVO],
            self::STATUS_RECONCILIACAO_PENDENTE => [self::STATUS_EMITIDA, self::STATUS_ERRO_DEFINITIVO],
            self::STATUS_EMITIDA => [self::STATUS_CANCELAMENTO_PENDENTE],
            self::STATUS_ERRO_TEMPORARIO => [self::STATUS_PENDENTE, self::STATUS_PROCESSANDO, self::STATUS_ERRO_DEFINITIVO],
            self::STATUS_ERRO_DEFINITIVO => [],
            self::STATUS_CANCELAMENTO_PENDENTE => [self::STATUS_CANCELADA, self::STATUS_ERRO_TEMPORARIO, self::STATUS_ERRO_DEFINITIVO],
            self::STATUS_CANCELADA => []
        ];
    }

    public static function transicaoPermitida($statusAtual, $novoStatus)
    {
        if($statusAtual === null || $statusAtual === $novoStatus){
            return true;
        }

        $transicoes = self::transicoesPermitidas();

        return in_array($novoStatus, $transicoes[$statusAtual] ?? [], true);
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
        $statusSolicitado = $opcoes['status'] ?? self::STATUS_PENDENTE_DADOS;
        $status = in_array($statusSolicitado, self::statusPermitidos(), true)
            ? $statusSolicitado
            : self::STATUS_PENDENTE_DADOS;

        $sql = $this->db->prepare("
            INSERT INTO nfse_emissoes (
                CLI_ID, COB_ID, NFE_ReferenciaPagamento, NFE_Status, NFE_IdempotencyKey,
                NFE_PrestadorCnpj, NFE_Ambiente, NFE_Competencia, NFE_ValorFiscal, NFE_DescricaoServico, NFE_Serie, NFE_DataReserva
            ) VALUES (
                :cliente, :cobranca, :referencia, :status, :idempotency,
                :prestador_cnpj, :ambiente, :competencia, :valor, :descricao, :serie, NOW()
            )
        ");

        try{
            $sql->execute([
                ':cliente' => $clienteId,
                ':cobranca' => $cobrancaId,
                ':referencia' => $cobranca['COB_ProviderPaymentId'] ?? null,
                ':status' => $status,
                ':idempotency' => $idempotencyKey,
                ':prestador_cnpj' => $opcoes['prestador_cnpj'] ?? (defined('NFSE_PRESTADOR_CNPJ') ? preg_replace('/\D/', '', (string) NFSE_PRESTADOR_CNPJ) : null),
                ':ambiente' => $opcoes['ambiente'] ?? (defined('NFSE_AMBIENTE') ? NFSE_AMBIENTE : 'production'),
                ':competencia' => $opcoes['competencia'] ?? date('Y-m-d'),
                ':valor' => $opcoes['valor'] ?? ($cobranca['COB_Valor'] ?? 0),
                ':descricao' => $opcoes['descricao'] ?? 'Mensalidade Disparador.net',
                ':serie' => $opcoes['serie'] ?? (defined('NFSE_DPS_SERIE') ? NFSE_DPS_SERIE : '900')
            ]);
        }catch(\PDOException $e){
            if(!$this->erroDuplicidade($e)){
                throw $e;
            }

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

    public function atualizarStatus($nfseId, $status, array $erro = [], $statusAtualEsperado = null)
    {
        if(!in_array($status, self::statusPermitidos(), true)){
            throw new \InvalidArgumentException('Status de NFS-e inválido.');
        }

        if(!self::transicaoPermitida($statusAtualEsperado, $status)){
            throw new \InvalidArgumentException('Transição de status de NFS-e não permitida.');
        }

        $whereStatus = $statusAtualEsperado !== null ? ' AND NFE_Status = :status_atual' : '';

        $sql = $this->db->prepare("\n            UPDATE nfse_emissoes\n            SET NFE_Status = :status,\n                NFE_UltimoErroTipo = :erro_tipo,\n                NFE_UltimoErroCodigo = :erro_codigo,\n                NFE_UltimoErroMensagem = :erro_mensagem,\n                NFE_DataAtualizacao = NOW()\n            WHERE NFE_ID = :id" . $whereStatus . "\n        ");

        $params = [
            ':status' => $status,
            ':erro_tipo' => $erro['tipo'] ?? null,
            ':erro_codigo' => $erro['codigo'] ?? null,
            ':erro_mensagem' => $this->sanitizarMensagem($erro['mensagem'] ?? null),
            ':id' => (int) $nfseId
        ];

        if($statusAtualEsperado !== null){
            $params[':status_atual'] = $statusAtualEsperado;
        }

        return $sql->execute($params);
    }


    public function buscarPorId($nfseId)
    {
        $sql = $this->db->prepare("SELECT * FROM nfse_emissoes WHERE NFE_ID = ? LIMIT 1");
        $sql->execute([(int) $nfseId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listarAdmin($status = null, $limit = 100)
    {
        $limit = max(1, min(200, (int) $limit));
        $params = [];
        $where = '';

        if($status !== null && $status !== ''){
            $where = 'WHERE n.NFE_Status = :status';
            $params[':status'] = $status;
        }

        $sql = $this->db->prepare("
            SELECT n.*, c.CLI_Nome, c.CLI_Email, cb.COB_Valor, cb.COB_Status, cb.COB_DataVencimento, cb.COB_DataPagamento
            FROM nfse_emissoes n
            LEFT JOIN clientes c ON c.CLI_ID = n.CLI_ID
            LEFT JOIN cobrancas cb ON cb.COB_ID = n.COB_ID
            {$where}
            ORDER BY n.NFE_ID DESC
            LIMIT {$limit}
        ");
        $sql->execute($params);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function prepararProcessamento($nfseId, $statusAtualEsperado)
    {
        return $this->atualizarStatus($nfseId, self::STATUS_PROCESSANDO, [], $statusAtualEsperado);
    }

    public function persistirSucessoEmissao($nfseId, array $resultado)
    {
        $sql = $this->db->prepare("
            UPDATE nfse_emissoes
            SET NFE_Status = :status,
                NFE_RequestIdEmissao = :request_id,
                NFE_IdDps = :id_dps,
                NFE_ChaveDps = :chave_dps,
                NFE_ChaveAcesso = :chave_acesso,
                NFE_DataEmissao = NOW(),
                NFE_Tentativas = NFE_Tentativas + 1,
                NFE_UltimoErroTipo = NULL,
                NFE_UltimoErroCodigo = NULL,
                NFE_UltimoErroMensagem = NULL,
                NFE_RetornoSanitizado = :retorno,
                NFE_DataAtualizacao = NOW()
            WHERE NFE_ID = :id
            AND NFE_Status = :status_atual
        ");

        return $sql->execute([
            ':status' => self::STATUS_EMITIDA,
            ':request_id' => $resultado['request_id'] ?? null,
            ':id_dps' => $resultado['id_dps'] ?? null,
            ':chave_dps' => $resultado['chave_dps'] ?? null,
            ':chave_acesso' => $resultado['chave_acesso'] ?? null,
            ':retorno' => json_encode($this->sanitizarArray($resultado['retorno_sanitizado'] ?? $resultado), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => (int) $nfseId,
            ':status_atual' => self::STATUS_PROCESSANDO
        ]);
    }

    public function persistirErroEmissao($nfseId, array $resultado, $statusAtualEsperado = self::STATUS_PROCESSANDO)
    {
        $status = !empty($resultado['incerto'])
            ? self::STATUS_RECONCILIACAO_PENDENTE
            : (!empty($resultado['temporario']) ? self::STATUS_ERRO_TEMPORARIO : self::STATUS_ERRO_DEFINITIVO);

        $sql = $this->db->prepare("
            UPDATE nfse_emissoes
            SET NFE_Status = :status,
                NFE_RequestIdEmissao = COALESCE(:request_id, NFE_RequestIdEmissao),
                NFE_Tentativas = NFE_Tentativas + 1,
                NFE_UltimoErroTipo = :erro_tipo,
                NFE_UltimoErroCodigo = :erro_codigo,
                NFE_UltimoErroMensagem = :erro_mensagem,
                NFE_RetornoSanitizado = :retorno,
                NFE_DataAtualizacao = NOW()
            WHERE NFE_ID = :id
            AND NFE_Status = :status_atual
        ");

        return $sql->execute([
            ':status' => $status,
            ':request_id' => $resultado['request_id'] ?? null,
            ':erro_tipo' => $resultado['tipo_erro'] ?? null,
            ':erro_codigo' => $resultado['error_code'] ?? null,
            ':erro_mensagem' => $this->sanitizarMensagem($resultado['error_message'] ?? null),
            ':retorno' => json_encode($this->sanitizarArray($resultado['retorno_sanitizado'] ?? $resultado), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => (int) $nfseId,
            ':status_atual' => $statusAtualEsperado
        ]);
    }

    public function persistirArquivoXml($nfseId, $pathRelativo, $hash)
    {
        $sql = $this->db->prepare("
            UPDATE nfse_emissoes
            SET NFE_XmlStoragePath = :path,
                NFE_XmlSha256 = :hash,
                NFE_DataAtualizacao = NOW()
            WHERE NFE_ID = :id
        ");

        return $sql->execute([
            ':path' => $this->normalizarPathRelativo($pathRelativo),
            ':hash' => $hash,
            ':id' => (int) $nfseId
        ]);
    }

    public function persistirArquivoPdf($nfseId, $pathRelativo, $hash)
    {
        $sql = $this->db->prepare("
            UPDATE nfse_emissoes
            SET NFE_PdfStoragePath = :path,
                NFE_PdfSha256 = :hash,
                NFE_DataAtualizacao = NOW()
            WHERE NFE_ID = :id
        ");

        return $sql->execute([
            ':path' => $this->normalizarPathRelativo($pathRelativo),
            ':hash' => $hash,
            ':id' => (int) $nfseId
        ]);
    }

    private function normalizarPathRelativo($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        $path = ltrim($path, '/');
        if(strpos($path, '..') !== false){
            throw new \InvalidArgumentException('Caminho de arquivo fiscal inválido.');
        }
        return $path;
    }

    private function sanitizarArray(array $dados)
    {
        if(class_exists('Services\\NfseSanitizer')){
            return \Services\NfseSanitizer::dados($dados);
        }
        return $dados;
    }

    public function sanitizarMensagem($mensagem)
    {
        if($mensagem === null){
            return null;
        }

        $mensagem = (string) $mensagem;
        $padroes = [
            '/Authorization\s*[:=]\s*Bearer\s+[^,;\s]+/i' => 'Authorization: Bearer ***',
            '/Bearer\s+[^,;\s]+/i' => 'Bearer ***',
            '/(API_AUTH_TOKEN|senhaCert|CERT_PASSWORD|password|senha|cert|PFX|base64)\s*[:=]\s*[^,;\s]+/i' => '$1=***',
            '/(\/[^\s,;]*)(cert|pfx|nfse)[^\s,;]*/i' => '[caminho_sensivel]'
        ];

        foreach($padroes as $padrao => $substituicao){
            $mensagem = preg_replace($padrao, $substituicao, $mensagem);
        }

        $mensagem = preg_replace('/[\r\n\t]+/', ' ', $mensagem);

        return mb_substr(trim($mensagem), 0, 1000);
    }

    private function erroDuplicidade(\PDOException $e)
    {
        $errorInfo = $e->errorInfo ?? [];
        $sqlState = (string) ($errorInfo[0] ?? $e->getCode());
        $driverCode = (string) ($errorInfo[1] ?? '');

        return $sqlState === '23000' || $driverCode === '1062';
    }
}
