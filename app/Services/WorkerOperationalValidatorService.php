<?php

namespace Services;

use Core\Database;
use Models\Cliente;
use Models\ConsumoMensal;
use Models\MetaConta;
use PDO;

class WorkerOperationalValidatorService
{
    private const DIAS_AVALIACAO = 7;
    private const LIMITE_MENSAGENS_AVALIACAO = 200;

    private $db;
    private $clienteModel;
    private $metaModel;
    private $consumoModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->clienteModel = new Cliente();
        $this->metaModel = new MetaConta();
        $this->consumoModel = new ConsumoMensal();
    }

    public function validarEnvio(int $clienteId, int $metaId, string $numero = ''): array
    {
        $cliente = $this->clienteModel->buscarComPlano($clienteId);

        if(!$cliente){
            return $this->resultado('bloqueio_definitivo', 'cliente_nao_encontrado', 'Cliente não encontrado.');
        }

        if(($cliente['CLI_Ativo'] ?? 'S') !== 'S'){
            return $this->resultado('bloqueio_definitivo', 'cliente_inativo', 'Cliente inativo.');
        }

        if(($cliente['CLI_StatusCadastro'] ?? '') !== 'ativo'){
            return $this->resultado('bloqueio_definitivo', 'cadastro_nao_ativo', 'Cadastro do cliente não está ativo.');
        }

        $financeiro = $this->validarFinanceiroTrial($cliente);

        if($financeiro['status'] !== 'permitido'){
            return $financeiro;
        }

        $limite = $this->validarLimiteMensagens($clienteId, $cliente);

        if($limite['status'] !== 'permitido'){
            return $limite;
        }

        $meta = $this->metaModel->buscarPorCliente($metaId, $clienteId);

        if(!$meta){
            return $this->resultado('bloqueio_definitivo', 'meta_conta_inativa', 'Conta Meta não encontrada ou inativa.');
        }

        if(empty($meta['MTA_Token'])){
            return $this->resultado('bloqueio_definitivo', 'meta_token_ausente', 'Token da conta Meta ausente.');
        }

        if(empty($meta['MTA_PhoneNumberId']) || empty($meta['MTA_UrlBase'])){
            return $this->resultado('bloqueio_definitivo', 'meta_configuracao_incompleta', 'Conta Meta sem configuração operacional completa.');
        }

        $statusMeta = strtolower((string) ($meta['MTA_Status'] ?? ''));
        if(in_array($statusMeta, ['erro', 'desconectado', 'requer_acao'], true)){
            return $this->resultado('bloqueio_temporario', 'meta_status_bloqueado', 'Conta Meta exige ação antes de enviar.');
        }

        $numeroNormalizado = preg_replace('/\D/', '', $numero);
        if($numeroNormalizado === '' || strlen($numeroNormalizado) < 10){
            return $this->resultado('bloqueio_definitivo', 'numero_invalido', 'Número de WhatsApp inválido para envio.');
        }

        return $this->resultado('permitido', null, null, [
            'cliente' => $cliente,
            'meta' => $meta
        ]);
    }

    private function validarFinanceiroTrial(array $cliente): array
    {
        $statusPagamento = $cliente['CLI_StatusPagamento'] ?? null;

        if($statusPagamento === 'pago'){
            return $this->resultado('permitido');
        }

        if($statusPagamento !== 'pendente'){
            return $this->resultado('bloqueio_temporario', 'financeiro_pendente', 'Cliente sem pagamento regularizado.');
        }

        if($this->clienteEmToleranciaFinanceira((int) $cliente['CLI_ID'])){
            return $this->resultado('permitido');
        }

        $dataLiberacao = $cliente['CLI_DataLiberacao'] ?? null;

        if(empty($dataLiberacao)){
            return $this->resultado('bloqueio_temporario', 'trial_nao_iniciado', 'Trial ainda não foi iniciado/liberado.');
        }

        $timestamp = strtotime((string) $dataLiberacao);

        if(!$timestamp){
            return $this->resultado('bloqueio_temporario', 'trial_data_invalida', 'Data de liberação do trial inválida.');
        }

        $dias = (int) floor((time() - $timestamp) / 86400);

        if($dias >= self::DIAS_AVALIACAO){
            return $this->resultado('bloqueio_temporario', 'trial_encerrado', 'Trial encerrado.');
        }

        $consumo = $this->consumoModel->buscarMesAtual((int) $cliente['CLI_ID']);
        $mensagens = (int) ($consumo['CMS_Mensagens'] ?? 0);

        if($mensagens >= self::LIMITE_MENSAGENS_AVALIACAO){
            return $this->resultado('bloqueio_temporario', 'trial_limite_mensagens', 'Limite de mensagens do trial atingido.');
        }

        return $this->resultado('permitido');
    }

    private function validarLimiteMensagens(int $clienteId, array $cliente): array
    {
        $limitePlano = (int) ($cliente['PLA_LimiteMensagens'] ?? 0);

        if($limitePlano <= 0){
            return $this->resultado('permitido');
        }

        $consumo = $this->consumoModel->buscarMesAtual($clienteId);
        $utilizadas = (int) ($consumo['CMS_Mensagens'] ?? 0);

        if($utilizadas >= $limitePlano){
            return $this->resultado('bloqueio_temporario', 'limite_mensal_atingido', 'Limite mensal de mensagens atingido.');
        }

        return $this->resultado('permitido');
    }

    private function clienteEmToleranciaFinanceira(int $clienteId): bool
    {
        $diasTolerancia = defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO')
            ? (int) FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO
            : 5;

        $sql = $this->db->prepare("
            SELECT COB_ID
            FROM cobrancas
            WHERE CLI_ID = ?
            AND COB_Status = 'vencido'
            AND COB_DataVencimento >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY COB_DataVencimento DESC
            LIMIT 1
        ");

        $sql->execute([$clienteId, $diasTolerancia]);

        return (bool) $sql->fetch(PDO::FETCH_ASSOC);
    }

    private function resultado(string $status, ?string $codigo = null, ?string $mensagem = null, array $extra = []): array
    {
        return array_merge([
            'status' => $status,
            'permitido' => $status === 'permitido',
            'codigo' => $codigo,
            'mensagem' => $mensagem
        ], $extra);
    }
}
