<?php

namespace Services;

use Models\Assinatura;
use Models\Cliente;
use Models\Cobranca;
use Models\FinanceiroTransacao;
use Models\MetaConta;
use Models\Plano;

class FinanceiroWorkflowService
{
    private $clientes;
    private $assinaturas;
    private $cobrancas;
    private $planos;
    private $asaas;
    private $recorrencia;
    private $transacao;
    private $metas;

    public function __construct($clientes = null, $assinaturas = null, $cobrancas = null, $planos = null, $asaas = null, $recorrencia = null, $transacao = null, $metas = null)
    {
        $this->clientes = $clientes ?: new Cliente();
        $this->assinaturas = $assinaturas ?: new Assinatura();
        $this->cobrancas = $cobrancas ?: new Cobranca();
        $this->planos = $planos ?: new Plano();
        $this->asaas = $asaas ?: new AsaasService();
        $this->recorrencia = $recorrencia ?: new FinanceiroRecorrenciaService();
        $this->transacao = $transacao ?: new FinanceiroTransacao();
        $this->metas = $metas ?: new MetaConta();
    }

    public function contratarPlano(int $clienteId, int $planoId, string $ciclo): array
    {
        if(!Plano::cicloValido($ciclo)){
            throw new \DomainException('Ciclo de cobrança inválido.');
        }
        $plano = $this->planos->buscar($planoId);
        if(!$plano){
            throw new \DomainException('Plano inválido.');
        }
        $limite = $this->metas->validarLimiteNumerosPlano($clienteId, $plano['PLA_LimiteNumeros']);
        if(empty($limite['permitido'])){
            throw new \DomainException($limite['mensagem']);
        }
        $pendente = $this->cobrancas->buscarPendentePorCliente($clienteId);
        if($pendente){
            if((int) $pendente['PLA_ID'] !== $planoId){
                throw new \DomainException('Já existe uma cobrança pendente para este cliente.');
            }
            $integracao = $this->integrarCobrancaAsaas($clienteId, (int) $pendente['COB_ID'], $plano);
            return array_merge($integracao, ['cobranca_id'=>(int) $pendente['COB_ID'], 'plano'=>$plano]);
        }

        $valor = Plano::valorPorCiclo($plano, $ciclo);
        $proxima = date('Y-m-d', strtotime('+' . Plano::mesesPorCiclo($ciclo) . ' months'));
        $cobrancaId = $this->transacao->executar(function() use ($clienteId, $plano, $ciclo, $valor, $proxima){
            $this->clientes->atualizarEstadoFinanceiro($clienteId, ['plano'=>$plano['PLA_ID'], 'status_pagamento'=>'pendente']);
            $this->assinaturas->criarOuAtualizarPorCliente($clienteId, $plano, 'pendente', ['ciclo'=>$ciclo, 'valor'=>$valor, 'proxima_cobranca'=>$proxima]);
            $assinatura = $this->assinaturas->buscarParaPagamento($clienteId, $plano['PLA_ID']);
            return $this->cobrancas->criar([
                'cliente'=>$clienteId, 'plano'=>$plano['PLA_ID'], 'assinatura'=>$assinatura['ASS_ID'] ?? null,
                'valor'=>$valor, 'vencimento'=>date('Y-m-d', strtotime('+3 days')), 'tipo'=>'mensalidade',
                'provider'=>'asaas', 'provider_status'=>'local_pendente'
            ]);
        });

        $integracao = $this->integrarCobrancaAsaas($clienteId, (int) $cobrancaId, $plano);
        $this->log('contratacao', ['cliente_id'=>$clienteId, 'cobranca_id'=>$cobrancaId, 'sucesso'=>$integracao['sucesso']]);
        return array_merge($integracao, ['cobranca_id'=>(int) $cobrancaId, 'plano'=>$plano]);
    }

    public function confirmarPagamentoManual(int $cobrancaId): array
    {
        $cobranca = $this->cobrancas->buscar($cobrancaId);
        if(!$cobranca || !in_array(strtolower((string) $cobranca['COB_Status']), ['pendente','vencido'], true)){
            throw new \DomainException('Não foi possível lançar o pagamento.');
        }
        $this->transacao->executar(function() use ($cobrancaId, $cobranca){
            $this->cobrancas->marcarPago($cobrancaId);
            $this->clientes->atualizarEstadoFinanceiro($cobranca['CLI_ID'], ['status_pagamento'=>'pago', 'status_cadastro'=>'ativo', 'liberar_se_vazio'=>true]);
            $assinatura = $this->assinaturas->buscarParaPagamento($cobranca['CLI_ID'], $cobranca['PLA_ID']);
            if($assinatura){ $this->assinaturas->ativar($assinatura['ASS_ID']); }
        });
        $this->log('pagamento_manual', ['cobranca_id'=>$cobrancaId]);
        return ['sucesso'=>true, 'cobranca'=>$cobranca];
    }

    public function processarPagamentoWebhook(array $payload): array
    {
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $paymentId = trim((string) ($payment['id'] ?? ''));
        if($paymentId === ''){ return ['processado'=>false, 'motivo'=>'payment_id_ausente']; }
        $cobranca = $this->cobrancas->buscarPorProviderPaymentId('asaas', $paymentId);
        if(!$cobranca){ return ['processado'=>false, 'motivo'=>'cobranca_nao_encontrada']; }

        $evento = trim((string) ($payload['event'] ?? ''));
        $providerStatus = trim((string) ($payment['status'] ?? ''));
        $eventId = trim((string) ($payload['id'] ?? $payload['eventId'] ?? ''));
        if($eventId === ''){
            $data = trim((string) ($payload['dateCreated'] ?? $payment['dateCreated'] ?? 'sem_data'));
            $eventId = hash('sha256', implode('|', ['asaas',$paymentId,$evento,$providerStatus,$data]));
        }
        $status = $this->statusCobrancaPorEvento($evento);

        return $this->transacao->executar(function() use ($cobranca, $eventId, $evento, $providerStatus, $payload, $status){
            $atualizada = $this->cobrancas->buscarParaAtualizacao($cobranca['COB_ID']);
            if(!$atualizada){ throw new \RuntimeException('Cobrança não encontrada durante o processamento.'); }
            $transicao = $this->resolverTransicaoWebhook(strtolower((string) $atualizada['COB_Status']), $evento, $status);
            $payloadSeguro = $this->payloadProviderSeguro($payload, $transicao);
            $registro = $this->cobrancas->registrarEventoProvider($cobranca['COB_ID'], 'asaas', $eventId, $evento, $providerStatus, $payloadSeguro);
            if($registro === 'duplicado'){
                return ['processado'=>false, 'duplicado'=>true];
            }
            if(!$transicao['aplicar']){
                return ['processado'=>true, 'ignorado'=>true, 'motivo'=>$transicao['motivo'], 'status'=>$transicao['atual']];
            }
            $status = $transicao['novo'];
            $dados = ['provider_status'=>$providerStatus, 'provider_payload'=>$payloadSeguro];
            if($status !== null){ $dados['status'] = $status; }
            if($status === 'pago'){ $dados['data_pagamento'] = date('Y-m-d H:i:s'); }
            $this->cobrancas->atualizarIntegracaoProvider($cobranca['COB_ID'], $dados);

            if($status === 'pago'){
                $this->clientes->atualizarEstadoFinanceiro($cobranca['CLI_ID'], ['status_pagamento'=>'pago','status_cadastro'=>'ativo','ativo'=>'S']);
                $this->ativarAssinaturaDaCobranca($cobranca);
            }elseif($status === 'vencido'){
                $this->clientes->atualizarEstadoFinanceiro($cobranca['CLI_ID'], ['status_pagamento'=>'pendente']);
            }elseif($evento === 'PAYMENT_REFUNDED'){
                // Reembolso afeta a cobrança e a situação financeira, mas a decisão
                // contratual permanece separada e não cancela assinaturas em massa.
                $this->clientes->atualizarEstadoFinanceiro($cobranca['CLI_ID'], ['status_pagamento'=>'pendente']);
            }
            $this->log('webhook_asaas', ['cobranca_id'=>$cobranca['COB_ID'], 'evento'=>$evento, 'status'=>$status]);
            return ['processado'=>true, 'status'=>$status];
        });
    }

    public function gerarCobrancasRecorrentes(): array
    {
        $resultado = ['assinaturas_processadas'=>0,'cobrancas_geradas'=>0,'cobrancas_ignoradas_duplicidade'=>0,'erros'=>0];
        foreach($this->assinaturas->listarParaRecorrencia() as $assinatura){
            $resultado['assinaturas_processadas']++;
            $vencimento = $assinatura['ASS_DataProximaCobranca'] ?: date('Y-m-d');
            $existente = $this->cobrancas->buscarRecorrente($assinatura['CLI_ID'], $assinatura['PLA_ID'], $vencimento, 'mensalidade', $assinatura['ASS_ID']);
            if($existente && !empty($existente['COB_ProviderPaymentId'])){
                $this->reconciliarProximaCobranca($assinatura, $vencimento);
                $resultado['cobrancas_ignoradas_duplicidade']++;
                continue;
            }
            try{
                $reserva = $existente
                    ? ['id'=>(int) $existente['COB_ID'], 'criada'=>false]
                    : $this->transacao->executar(function() use ($assinatura, $vencimento){
                    return $this->cobrancas->criarRecorrenteIdempotente([
                        'cliente'=>$assinatura['CLI_ID'], 'plano'=>$assinatura['PLA_ID'], 'assinatura'=>$assinatura['ASS_ID'],
                        'valor'=>$assinatura['ASS_Valor'], 'vencimento'=>$vencimento, 'tipo'=>'mensalidade',
                        'provider'=>'asaas', 'provider_status'=>'local_pendente'
                    ]);
                });
                $cobrancaId = $reserva['id'];
                $plano = $this->planos->buscar($assinatura['PLA_ID']);
                $integracao = $this->integrarCobrancaAsaas($assinatura['CLI_ID'], (int) $cobrancaId, $plano ?: []);
                if(!$integracao['sucesso']){
                    $resultado['erros']++;
                    continue;
                }
                $this->reconciliarProximaCobranca($assinatura, $vencimento);
                $resultado['cobrancas_geradas']++;
                $this->log('recorrencia', ['assinatura_id'=>$assinatura['ASS_ID'], 'cobranca_id'=>$cobrancaId, 'integrada'=>$integracao['sucesso']]);
            }catch(\Throwable $e){
                $resultado['erros']++;
                $this->log('erro_recorrencia', ['assinatura_id'=>$assinatura['ASS_ID'], 'erro'=>$e->getMessage()]);
            }
        }
        return $resultado;
    }

    public function processarVencimentos(): array
    {
        $resultado = ['cobrancas_vencidas'=>0,'assinaturas_vencidas'=>0,'clientes_atualizados'=>0,'dias_tolerancia'=>$this->recorrencia->diasTolerancia()];
        $clientes = [];
        foreach($this->cobrancas->listarPendentesVencidas() as $cobranca){
            $this->transacao->executar(function() use ($cobranca, &$resultado, &$clientes){
                $this->cobrancas->atualizarIntegracaoProvider($cobranca['COB_ID'], ['status'=>'vencido']);
                $resultado['cobrancas_vencidas']++;
                $clienteId = (int) $cobranca['CLI_ID'];
                if(!isset($clientes[$clienteId])){
                    $assinatura = $this->assinaturas->buscarVigentePorCliente($clienteId);
                    if($assinatura){ $this->assinaturas->marcarVencida($assinatura['ASS_ID']); $resultado['assinaturas_vencidas']++; }
                    $this->clientes->atualizarEstadoFinanceiro($clienteId, ['status_pagamento'=>'pendente']);
                    $clientes[$clienteId] = true;
                    $resultado['clientes_atualizados']++;
                }
            });
        }
        $this->log('vencimentos', $resultado);
        return $resultado;
    }

    public function cancelarContrato(int $clienteId, string $motivo = ''): array
    {
        $this->transacao->executar(function() use ($clienteId){
            $this->assinaturas->cancelarVigentesPorCliente($clienteId);
            $this->cobrancas->cancelarPendentesPorCliente($clienteId);
            $this->clientes->atualizarEstadoFinanceiro($clienteId, ['status_pagamento'=>'pendente', 'status_cadastro'=>'suspenso']);
        });
        $this->log('cancelamento_contrato', ['cliente_id'=>$clienteId, 'motivo'=>$motivo]);
        return ['sucesso'=>true];
    }

    public function alterarPlanoCliente(int $clienteId, int $planoId, string $ciclo): array
    {
        if(!Plano::cicloValido($ciclo)){ throw new \DomainException('Ciclo de cobrança inválido.'); }
        $plano = $this->planos->buscar($planoId);
        if(!$plano){ throw new \DomainException('Plano inválido.'); }
        $limite = $this->metas->validarLimiteNumerosPlano($clienteId, $plano['PLA_LimiteNumeros']);
        if(empty($limite['permitido'])){ throw new \DomainException($limite['mensagem']); }
        $valor = Plano::valorPorCiclo($plano, $ciclo);
        $proxima = date('Y-m-d', strtotime('+' . Plano::mesesPorCiclo($ciclo) . ' months'));
        $this->transacao->executar(function() use ($clienteId, $plano, $ciclo, $valor, $proxima){
            $this->clientes->atualizarEstadoFinanceiro($clienteId, ['plano'=>$plano['PLA_ID']]);
            $this->assinaturas->criarOuAtualizarPorCliente($clienteId, $plano, 'ativa', ['ciclo'=>$ciclo,'valor'=>$valor,'proxima_cobranca'=>$proxima]);
        });
        $this->log('alteracao_plano', ['cliente_id'=>$clienteId,'plano_id'=>$planoId,'ciclo'=>$ciclo]);
        return ['sucesso'=>true];
    }

    public function reativarContrato(int $clienteId): array
    {
        $assinatura = $this->assinaturas->buscarUltimaPorCliente($clienteId);
        if(!$assinatura){ throw new \DomainException('Cliente não possui assinatura para reativação.'); }
        $plano = $this->planos->buscar($assinatura['PLA_ID']);
        if(!$plano){ throw new \DomainException('Plano da assinatura não está disponível.'); }
        $ciclo = $assinatura['ASS_Ciclo'] ?: $plano['PLA_Periodicidade'];
        $valor = $assinatura['ASS_Valor'] ?: Plano::valorPorCiclo($plano, $ciclo);
        $pendente = $this->cobrancas->buscarPendentePorCliente($clienteId);
        if($pendente && (int) $pendente['PLA_ID'] === (int) $plano['PLA_ID']){
            $this->clientes->atualizarEstadoFinanceiro($clienteId, ['ativo'=>'S','status_cadastro'=>'suspenso','status_pagamento'=>'pendente','plano'=>$plano['PLA_ID']]);
            $integracao = $this->integrarCobrancaAsaas($clienteId, (int) $pendente['COB_ID'], $plano);
            return array_merge($integracao, ['cobranca_id'=>(int) $pendente['COB_ID']]);
        }
        $cobrancaId = $this->transacao->executar(function() use ($clienteId, $assinatura, $plano, $ciclo, $valor){
            $this->cobrancas->cancelarPendentesPorCliente($clienteId);
            $this->assinaturas->criarOuAtualizarPorCliente($clienteId, $plano, 'pendente', ['ciclo'=>$ciclo,'valor'=>$valor,'proxima_cobranca'=>date('Y-m-d', strtotime('+' . Plano::mesesPorCiclo($ciclo) . ' months'))]);
            $atual = $this->assinaturas->buscarParaPagamento($clienteId, $plano['PLA_ID']);
            $this->clientes->atualizarEstadoFinanceiro($clienteId, ['ativo'=>'S','status_cadastro'=>'suspenso','status_pagamento'=>'pendente','plano'=>$plano['PLA_ID']]);
            return $this->cobrancas->criar(['cliente'=>$clienteId,'plano'=>$plano['PLA_ID'],'assinatura'=>$atual['ASS_ID'] ?? null,'valor'=>$valor,'vencimento'=>date('Y-m-d', strtotime('+3 days')),'tipo'=>'mensalidade','provider'=>'asaas','provider_status'=>'local_pendente']);
        });
        $integracao = $this->integrarCobrancaAsaas($clienteId, (int) $cobrancaId, $plano);
        $this->log('reativacao_contrato', ['cliente_id'=>$clienteId,'cobranca_id'=>$cobrancaId,'integrada'=>$integracao['sucesso']]);
        return array_merge($integracao, ['cobranca_id'=>(int) $cobrancaId]);
    }

    public function cancelarAssinatura(int $assinaturaId, ?string $motivo = null): array
    {
        $assinatura = $this->assinaturas->buscarPorId($assinaturaId);
        if(!$assinatura){ throw new \DomainException('Assinatura não encontrada.'); }
        $this->assinaturas->cancelar($assinaturaId);
        $this->log('cancelamento_assinatura', ['assinatura_id'=>$assinaturaId, 'motivo'=>$motivo]);
        return ['sucesso'=>true];
    }

    private function integrarCobrancaAsaas(int $clienteId, int $cobrancaId, array $plano): array
    {
        return $this->cobrancas->comLockIntegracao($cobrancaId, function() use ($clienteId, $cobrancaId, $plano){
            $cliente = $this->clientes->buscar($clienteId);
            $cobranca = $this->cobrancas->buscar($cobrancaId);
            if(!$cliente || !$cobranca){ throw new \RuntimeException('Cliente ou cobrança não encontrada.'); }
            if(!empty($cobranca['COB_ProviderPaymentId'])){
                return ['sucesso'=>true,'reconciliada'=>true,'mensagem'=>'Cobrança já integrada.'];
            }

            $customerId = trim((string) ($cliente['CLI_ProviderCustomerId'] ?? ''));
            if($customerId === ''){
                $respostaCliente = $this->asaas->criarOuAtualizarCliente($cliente);
                if(empty($respostaCliente['sucesso']) || empty($respostaCliente['response']['id'])){
                    $this->cobrancas->atualizarIntegracaoProvider($cobrancaId, ['provider'=>'asaas','provider_status'=>'erro_cliente','provider_payload'=>$this->payloadProviderSeguro($respostaCliente['response'] ?? [])]);
                    return ['sucesso'=>false,'mensagem'=>'Não foi possível gerar a cobrança automaticamente. Verifique os dados cadastrais ou entre em contato com o suporte.'];
                }
                $customerId = $respostaCliente['response']['id'];
                $this->clientes->atualizarProviderPagamento($clienteId, 'asaas', $customerId);
                $cliente['CLI_ProviderCustomerId'] = $customerId;
            }

            $referencia = 'cobranca_' . $cobrancaId;
            $consulta = $this->asaas->buscarCobrancaPorReferenciaExterna($referencia);
            if(empty($consulta['sucesso'])){
                $this->cobrancas->atualizarIntegracaoProvider($cobrancaId, ['provider'=>'asaas','provider_customer_id'=>$customerId,'provider_status'=>'erro_reconciliacao','provider_payload'=>$this->payloadProviderSeguro($consulta['response'] ?? [])]);
                return ['sucesso'=>false,'mensagem'=>'Não foi possível confirmar a situação da cobrança no Asaas. Tente novamente em instantes.'];
            }
            $pagamento = $this->primeiraCobrancaEncontrada($consulta);
            $reconciliada = (bool) $pagamento;
            if(!$pagamento){
                $cobranca['descricao'] = 'Mensalidade ' . ($plano['PLA_Nome'] ?? 'Disparador.net');
                $resposta = $this->asaas->criarCobranca($cliente, $cobranca);
                if(empty($resposta['sucesso']) || empty($resposta['response']['id'])){
                    $this->cobrancas->atualizarIntegracaoProvider($cobrancaId, ['provider'=>'asaas','provider_customer_id'=>$customerId,'provider_status'=>'erro_cobranca','provider_payload'=>$this->payloadProviderSeguro($resposta['response'] ?? [])]);
                    return ['sucesso'=>false,'mensagem'=>'Plano selecionado, mas o Asaas não retornou o link de pagamento. Tente novamente em instantes ou fale com o suporte.'];
                }
                $pagamento = $resposta['response'];
            }

            $pix = $this->asaas->buscarPixQrCode($pagamento['id']);
            $pixDados = !empty($pix['sucesso']) && is_array($pix['response']) ? $pix['response'] : [];
            $this->cobrancas->atualizarIntegracaoProvider($cobrancaId, ['provider'=>'asaas','provider_customer_id'=>$customerId,'provider_payment_id'=>$pagamento['id'],'provider_status'=>$pagamento['status'] ?? null,'provider_payload'=>$this->payloadProviderSeguro($pagamento),'link_pagamento'=>$pagamento['invoiceUrl'] ?? ($pagamento['bankSlipUrl'] ?? null),'pix_copia_cola'=>$pixDados['payload'] ?? null,'qr_code'=>$pixDados['encodedImage'] ?? null,'linha_digitavel'=>$pagamento['identificationField'] ?? null,'status'=>'pendente']);
            return ['sucesso'=>true,'reconciliada'=>$reconciliada,'mensagem'=>'Plano selecionado. A cobrança foi criada e o link de pagamento está disponível.'];
        });
    }

    private function primeiraCobrancaEncontrada(array $resposta): ?array
    {
        if(empty($resposta['sucesso']) || !is_array($resposta['response'] ?? null)){
            return null;
        }
        $dados = $resposta['response']['data'] ?? [];
        return is_array($dados) && isset($dados[0]) && is_array($dados[0]) ? $dados[0] : null;
    }

    private function reconciliarProximaCobranca(array $assinatura, string $cicloProcessado): void
    {
        $proxima = $this->recorrencia->calcularProximaData($assinatura['ASS_Ciclo'], $cicloProcessado);
        $this->assinaturas->avancarProximaCobrancaSeCiclo($assinatura['ASS_ID'], $cicloProcessado, $proxima);
    }

    private function ativarAssinaturaDaCobranca(array $cobranca): void
    {
        $id = (int) ($cobranca['ASS_ID'] ?? 0);
        if($id <= 0){
            $assinatura = $this->assinaturas->buscarPendenteMaisRecente($cobranca['CLI_ID'], $cobranca['PLA_ID']);
            if(!$assinatura){ return; }
            $id = (int) $assinatura['ASS_ID'];
            $this->cobrancas->vincularAssinatura($cobranca['COB_ID'], $id);
        }
        $this->assinaturas->ativar($id);
    }

    private function statusCobrancaPorEvento(string $evento): ?string
    {
        return ['PAYMENT_RECEIVED'=>'pago','PAYMENT_CONFIRMED'=>'pago','PAYMENT_OVERDUE'=>'vencido','PAYMENT_DELETED'=>'cancelado','PAYMENT_REFUNDED'=>'cancelado'][$evento] ?? null;
    }

    private function resolverTransicaoWebhook(string $atual, string $evento, ?string $novo): array
    {
        if($novo === null){ return ['aplicar'=>false,'atual'=>$atual,'novo'=>$atual,'motivo'=>'evento_desconhecido']; }
        if($atual === $novo){ return ['aplicar'=>false,'atual'=>$atual,'novo'=>$atual,'motivo'=>'estado_ja_aplicado']; }
        if($atual === 'pago' && $evento !== 'PAYMENT_REFUNDED'){
            return ['aplicar'=>false,'atual'=>$atual,'novo'=>$atual,'motivo'=>'transicao_regressiva'];
        }
        if($atual === 'cancelado'){
            return ['aplicar'=>false,'atual'=>$atual,'novo'=>$atual,'motivo'=>'estado_terminal'];
        }
        $permitidas = [
            'pendente'=>['pago','vencido','cancelado'],
            'vencido'=>['pago','cancelado'],
            'pago'=>['cancelado']
        ];
        $aplicar = in_array($novo, $permitidas[$atual] ?? [], true);
        return ['aplicar'=>$aplicar,'atual'=>$atual,'novo'=>$aplicar ? $novo : $atual,'motivo'=>$aplicar ? 'aplicado' : 'transicao_invalida'];
    }

    private function payloadProviderSeguro(array $payload, ?array $decisao = null): string
    {
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : $payload;
        return json_encode(['event'=>$payload['event'] ?? null,'id'=>$payload['id'] ?? null,'decisao'=>$decisao,'payment'=>array_intersect_key($payment, array_flip(['id','customer','status','value','netValue','billingType','dueDate','paymentDate','clientPaymentDate','confirmedDate','invoiceUrl','bankSlipUrl','externalReference']))], JSON_UNESCAPED_UNICODE);
    }

    private function log(string $evento, array $dados): void
    {
        $dir = function_exists('diretorioLogsProjeto') ? diretorioLogsProjeto() : dirname(__DIR__, 2) . '/storage/logs';
        if(!is_dir($dir)){ mkdir($dir, 0770, true); }
        unset($dados['token'], $dados['access_token'], $dados['password']);
        error_log(json_encode(['data'=>date('Y-m-d H:i:s'),'evento'=>$evento,'dados'=>$dados], JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $dir . '/financeiro-workflow.log');
    }
}
