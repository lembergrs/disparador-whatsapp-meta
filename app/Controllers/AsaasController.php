<?php

namespace Controllers;

use Core\Controller;
use Models\Cliente;
use Models\Cobranca;

class AsaasController extends Controller
{
    public function webhook()
    {
        if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
            $this->responderJson(['sucesso' => false, 'erro' => 'Método não permitido'], 405);
        }

        $corpoBruto = file_get_contents('php://input');
        $payload = json_decode($corpoBruto ?: '', true);

        if(!is_array($payload)){
            $payload = [];
        }

        $validacaoToken = $this->validarTokenWebhook();
        $this->registrarWebhook($payload, $validacaoToken);

        if(!$validacaoToken['valido']){
            $this->responderJson(['sucesso' => false, 'erro' => 'Token inválido'], 403);
        }

        $this->processarEventoPagamento($payload);

        $this->responderJson(['sucesso' => true], 200);
    }


    private function processarEventoPagamento($payload)
    {
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $providerPaymentId = trim((string) ($payment['id'] ?? ''));

        if($providerPaymentId === ''){
            return;
        }

        $evento = trim((string) ($payload['event'] ?? ''));
        $providerStatus = trim((string) ($payment['status'] ?? ''));
        $cobrancaModel = new Cobranca();
        $cobranca = $cobrancaModel->buscarPorProviderPaymentId('asaas', $providerPaymentId);

        if(!$cobranca){
            return;
        }

        $providerEventId = $this->obterProviderEventId($payload, $providerPaymentId, $evento, $providerStatus);
        $payloadSeguro = $this->payloadProviderSeguro($payload);

        $registroEvento = $cobrancaModel->registrarEventoProvider(
            $cobranca['COB_ID'],
            'asaas',
            $providerEventId,
            $evento,
            $providerStatus,
            $payloadSeguro
        );

        if($registroEvento === 'duplicado'){
            return;
        }

        $statusLocal = $this->mapearStatusCobranca($evento);
        $dadosAtualizacao = [
            'provider_status' => $providerStatus,
            'provider_payload' => $payloadSeguro
        ];

        if($statusLocal !== null){
            $dadosAtualizacao['status'] = $statusLocal;
        }

        if(in_array($evento, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)){
            $dadosAtualizacao['data_pagamento'] = date('Y-m-d H:i:s');
        }

        $cobrancaModel->atualizarIntegracaoProvider($cobranca['COB_ID'], $dadosAtualizacao);

        if(in_array($evento, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)){
            $clienteModel = new Cliente();
            $clienteModel->marcarPagamentoProviderConfirmado($cobranca['CLI_ID']);
        }
    }

    private function mapearStatusCobranca($evento)
    {
        $mapa = [
            'PAYMENT_RECEIVED' => 'pago',
            'PAYMENT_CONFIRMED' => 'pago',
            'PAYMENT_OVERDUE' => 'vencido',
            'PAYMENT_DELETED' => 'cancelado',
            'PAYMENT_REFUNDED' => 'cancelado'
        ];

        return $mapa[$evento] ?? null;
    }

    private function obterProviderEventId($payload, $paymentId, $evento, $status)
    {
        $eventId = trim((string) ($payload['id'] ?? $payload['eventId'] ?? ''));

        if($eventId !== ''){
            return $eventId;
        }

        $dataEvento = trim((string) ($payload['dateCreated'] ?? $payload['payment']['dateCreated'] ?? date('Y-m-d H:i:s')));

        return hash('sha256', implode('|', ['asaas', $paymentId, $evento, $status, $dataEvento]));
    }

    private function payloadProviderSeguro($payload)
    {
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];

        $seguro = [
            'event' => $payload['event'] ?? null,
            'id' => $payload['id'] ?? null,
            'payment' => [
                'id' => $payment['id'] ?? null,
                'customer' => $payment['customer'] ?? null,
                'status' => $payment['status'] ?? null,
                'value' => $payment['value'] ?? null,
                'netValue' => $payment['netValue'] ?? null,
                'billingType' => $payment['billingType'] ?? null,
                'dueDate' => $payment['dueDate'] ?? null,
                'paymentDate' => $payment['paymentDate'] ?? null,
                'clientPaymentDate' => $payment['clientPaymentDate'] ?? null,
                'confirmedDate' => $payment['confirmedDate'] ?? null,
                'invoiceUrl' => $payment['invoiceUrl'] ?? null,
                'bankSlipUrl' => $payment['bankSlipUrl'] ?? null,
                'externalReference' => $payment['externalReference'] ?? null
            ]
        ];

        return json_encode($seguro, JSON_UNESCAPED_UNICODE);
    }

    private function validarTokenWebhook()
    {
        $tokenConfigurado = trim((string) ASAAS_WEBHOOK_TOKEN);
        $tokenRecebido = $this->obterHeader('asaas-access-token');

        // O Asaas envia o authToken configurado no webhook no header
        // "asaas-access-token". Se a documentação mudar, ajustar somente este ponto.
        // Nunca validar token via query string.
        if($tokenConfigurado === ''){
            return ['valido' => true, 'resultado' => 'token_nao_configurado'];
        }

        if($tokenRecebido === null || trim($tokenRecebido) === ''){
            return ['valido' => false, 'resultado' => 'token_ausente'];
        }

        return [
            'valido' => hash_equals($tokenConfigurado, trim($tokenRecebido)),
            'resultado' => hash_equals($tokenConfigurado, trim($tokenRecebido)) ? 'token_valido' : 'token_invalido'
        ];
    }

    private function obterHeader($nome)
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        foreach($headers as $header => $valor){
            if(strtolower($header) === strtolower($nome)){
                return $valor;
            }
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $nome));

        return $_SERVER[$serverKey] ?? null;
    }

    private function registrarWebhook($payload, $validacaoToken)
    {
        $diretorioLog = diretorioLogsProjeto();

        if(!is_dir($diretorioLog)){
            mkdir($diretorioLog, 0770, true);
        }

        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $customer = $payment['customer'] ?? ($payload['customer'] ?? null);

        $linha = [
            'data_hora' => date('Y-m-d H:i:s'),
            'evento' => $this->limparValorLog($payload['event'] ?? ''),
            'payment_id' => $this->limparValorLog($payment['id'] ?? ''),
            'customer_id' => $this->limparValorLog($customer ?? ''),
            'status' => $this->limparValorLog($payment['status'] ?? ''),
            'valor' => $this->limparValorLog($payment['value'] ?? ''),
            'ip' => $this->limparValorLog($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => $this->limparValorLog($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'validacao' => $validacaoToken['resultado']
        ];

        error_log(json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $diretorioLog . '/asaas-webhook.log');
    }

    private function limparValorLog($valor)
    {
        $valor = is_scalar($valor) ? (string) $valor : '';
        $valor = preg_replace('/[\r\n\t]+/', ' ', $valor);

        return mb_substr($valor, 0, 255);
    }

    private function responderJson($dados, $status)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
