<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Core\Database;
use Models\Plano;
use Models\Cobranca;
use Models\MetaConta;
use Models\Assinatura;
use Models\Cliente;
use Services\AsaasService;

class FinanceiroController extends Controller
{
    public function index()
    {
        Auth::clienteAdmin();

        $usuario = Auth::usuario();

        $planoModel = new Plano();
        $cobrancaModel = new Cobranca();
        $metaContaModel = new MetaConta();
        $assinaturaModel = new Assinatura();

        $planos = $planoModel->listarAtivos();
        $numerosAtivos =
            $metaContaModel->contarAtivasPorCliente(
                $usuario['CLI_ID']
            );

        $cobranca = $cobrancaModel
            ->buscarPendentePorCliente($usuario['CLI_ID']);

        $excedenteModel =
            new \Models\ExcedenteMensal();

        $excedente =
            $excedenteModel->buscarMesAtual(
                $usuario['CLI_ID']
            );

        $assinaturaAtual =
            $assinaturaModel->buscarAtualPorCliente(
                $usuario['CLI_ID']
            );

        $this->view(
            'financeiro/index',
            [
                'titulo' => 'Financeiro',
                'planos' => $planos,
                'cobranca' => $cobranca,
                'excedente' => $excedente,
                'numerosAtivos' => $numerosAtivos,
                'assinaturaAtual' => $assinaturaAtual
            ]
        );
    }

    public function escolherPlano()
    {
        $this->validarCsrfPost();

        Auth::clienteAdmin();

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->redirect('financeiro');
        }

        $usuario = Auth::usuario();

        $planoId = (int) ($_POST['plano'] ?? 0);
        $ciclo = $_POST['ciclo'] ?? 'mensal';

        if(!Plano::cicloValido($ciclo)){

            Session::flash(
                'error',
                'Ciclo de cobrança inválido.'
            );

            $this->redirect('financeiro');
        }

        $cobrancaModel = new Cobranca();

        $cobrancaExistente =
            $cobrancaModel->buscarPendentePorCliente(
                $usuario['CLI_ID']
            );

        if($cobrancaExistente){

            Session::flash(
                'error',
                'Já existe uma cobrança pendente para este cliente.'
            );

            $this->redirect('financeiro');

        }

        $planoModel = new Plano();
        $plano = $planoModel->buscar($planoId);

        if(!$plano){

            Session::flash(
                'error',
                'Plano inválido.'
            );

            $this->redirect('financeiro');
        }

        $metaContaModel = new MetaConta();
        $validacaoNumeros =
            $metaContaModel->validarLimiteNumerosPlano(
                $usuario['CLI_ID'],
                $plano['PLA_LimiteNumeros']
            );

        if(!$validacaoNumeros['permitido']){

            Session::flash(
                'error',
                $validacaoNumeros['mensagem']
            );

            $this->redirect('financeiro');
        }

        $valorCiclo = Plano::valorPorCiclo($plano, $ciclo);
        $proximaCobranca = date(
            'Y-m-d',
            strtotime('+' . Plano::mesesPorCiclo($ciclo) . ' months')
        );

        $db = Database::getInstance();

        $db->beginTransaction();

        try{

            $sql = $db->prepare("
                UPDATE clientes
                SET
                    CLI_Plano_DR = ?,
                    CLI_StatusPagamento = 'pendente'
                WHERE CLI_ID = ?
            ");

            $sql->execute([
                $plano['PLA_ID'],
                $usuario['CLI_ID']
            ]);

            $cobrancaId = $cobrancaModel->criar([
                'cliente' => $usuario['CLI_ID'],
                'plano' => $plano['PLA_ID'],
                'valor' => $valorCiclo,
                'vencimento' => date('Y-m-d', strtotime('+3 days')),
                'tipo' => 'mensalidade',
                'provider' => 'asaas',
                'provider_status' => 'local_pendente'
            ]);

            $resultadoAsaas = $this->sincronizarCobrancaAsaas(
                $usuario['CLI_ID'],
                $cobrancaId,
                $plano
            );

            $assinaturaModel = new Assinatura();
            $assinaturaModel->criarOuAtualizarPorCliente(
                $usuario['CLI_ID'],
                $plano,
                'pendente',
                [
                    'ciclo' => $ciclo,
                    'valor' => $valorCiclo,
                    'proxima_cobranca' => $proximaCobranca
                ]
            );

            $db->commit();

            $_SESSION['usuario']['CLI_Plano_DR'] =
                $plano['PLA_ID'];

            $_SESSION['usuario']['CLI_StatusPagamento'] =
                'pendente';

            Session::flash(
                $resultadoAsaas['sucesso'] ? 'success' : 'warning',
                $resultadoAsaas['mensagem']
            );

        }catch(\Exception $e){

            $db->rollBack();

            Session::flash(
                'error',
                'Erro ao gerar cobrança.'
            );
        }

        $this->redirect('financeiro');
    }



    private function sincronizarCobrancaAsaas($clienteId, $cobrancaId, $plano)
    {
        $clienteModel = new Cliente();
        $cobrancaModel = new Cobranca();
        $asaasService = new AsaasService();

        $cliente = $clienteModel->buscar($clienteId);
        $cobranca = $cobrancaModel->buscar($cobrancaId);

        if(!$cliente || !$cobranca){
            return [
                'sucesso' => false,
                'mensagem' => 'Plano selecionado, mas não foi possível sincronizar a cobrança neste momento.'
            ];
        }

        $providerCustomerId = trim((string) ($cliente['CLI_ProviderCustomerId'] ?? ''));

        if($providerCustomerId === ''){
            $resultadoCliente = $asaasService->criarOuAtualizarCliente($cliente);

            if(!$resultadoCliente['sucesso'] || empty($resultadoCliente['response']['id'])){
                $cobrancaModel->atualizarIntegracaoProvider($cobrancaId, [
                    'provider' => 'asaas',
                    'provider_status' => 'erro_cliente',
                    'provider_payload' => $this->payloadProviderSeguro($resultadoCliente['response'] ?? [])
                ]);

                return [
                    'sucesso' => false,
                    'mensagem' => 'Plano selecionado, mas não foi possível gerar o link de pagamento agora. Nossa equipe foi notificada para revisar a cobrança.'
                ];
            }

            $providerCustomerId = $resultadoCliente['response']['id'];
            $clienteModel->atualizarProviderPagamento($clienteId, 'asaas', $providerCustomerId);
            $cliente['CLI_ProviderPagamento'] = 'asaas';
            $cliente['CLI_ProviderCustomerId'] = $providerCustomerId;
        }

        $cobranca['descricao'] = 'Mensalidade ' . ($plano['PLA_Nome'] ?? 'Disparador.net');
        $resultadoCobranca = $asaasService->criarCobranca($cliente, $cobranca);

        if(!$resultadoCobranca['sucesso'] || empty($resultadoCobranca['response']['id'])){
            $cobrancaModel->atualizarIntegracaoProvider($cobrancaId, [
                'provider' => 'asaas',
                'provider_customer_id' => $providerCustomerId,
                'provider_status' => 'erro_cobranca',
                'provider_payload' => $this->payloadProviderSeguro($resultadoCobranca['response'] ?? [])
            ]);

            return [
                'sucesso' => false,
                'mensagem' => 'Plano selecionado, mas o Asaas não retornou o link de pagamento. Tente novamente em instantes ou fale com o suporte.'
            ];
        }

        $pagamento = $resultadoCobranca['response'];
        $pix = [];

        if(!empty($pagamento['id'])){
            $resultadoPix = $asaasService->buscarPixQrCode($pagamento['id']);

            if($resultadoPix['sucesso'] && is_array($resultadoPix['response'])){
                $pix = $resultadoPix['response'];
            }
        }

        $cobrancaModel->atualizarIntegracaoProvider($cobrancaId, [
            'provider' => 'asaas',
            'provider_customer_id' => $providerCustomerId,
            'provider_payment_id' => $pagamento['id'] ?? null,
            'provider_status' => $pagamento['status'] ?? null,
            'provider_payload' => $this->payloadProviderSeguro($pagamento),
            'link_pagamento' => $pagamento['invoiceUrl'] ?? ($pagamento['bankSlipUrl'] ?? null),
            'pix_copia_cola' => $pix['payload'] ?? null,
            'qr_code' => $pix['encodedImage'] ?? null,
            'linha_digitavel' => $pagamento['identificationField'] ?? null,
            'status' => 'pendente'
        ]);

        return [
            'sucesso' => true,
            'mensagem' => 'Plano selecionado. A cobrança foi criada e o link de pagamento está disponível.'
        ];
    }

    private function payloadProviderSeguro($payload)
    {
        if(!is_array($payload)){
            return null;
        }

        $permitidos = [
            'id', 'status', 'billingType', 'value', 'netValue', 'dueDate',
            'invoiceUrl', 'bankSlipUrl', 'transactionReceiptUrl',
            'customer', 'externalReference', 'description', 'dateCreated',
            'paymentDate', 'clientPaymentDate', 'confirmedDate', 'deleted'
        ];

        return json_encode(
            array_intersect_key($payload, array_flip($permitidos)),
            JSON_UNESCAPED_UNICODE
        );
    }

}
