<?php

namespace Services;

use Models\Assinatura;
use Models\Cliente;
use Models\Cobranca;
use Models\FinanceiroTransacao;
use Models\MetaConta;
use Models\Plano;
use Services\Indicacao\IndicacaoDescontoService;
use Services\Indicacao\IndicacaoAuditoriaService;
use Services\Indicacao\IndicacaoPrimeiroPagamentoService;

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
    private $descontosIndicacao;
    private $auditoriaIndicacao;
    private $primeiroPagamentoIndicacao;
    private $descontoBoasVindas;
    private $notificacoesFinanceiras;

    public function __construct($clientes = null, $assinaturas = null, $cobrancas = null, $planos = null, $asaas = null, $recorrencia = null, $transacao = null, $metas = null, $descontosIndicacao = null, $auditoriaIndicacao = null, $primeiroPagamentoIndicacao = null, $descontoBoasVindas = null, $notificacoesFinanceiras = null)
    {
        $this->clientes = $clientes ?: new Cliente();
        $this->assinaturas = $assinaturas ?: new Assinatura();
        $this->cobrancas = $cobrancas ?: new Cobranca();
        $this->planos = $planos ?: new Plano();
        $this->asaas = $asaas ?: new AsaasService();
        $this->recorrencia = $recorrencia ?: new FinanceiroRecorrenciaService();
        $this->transacao = $transacao ?: new FinanceiroTransacao();
        $this->metas = $metas ?: new MetaConta();
        $this->descontosIndicacao = $descontosIndicacao;
        $this->auditoriaIndicacao = $auditoriaIndicacao;
        $this->primeiroPagamentoIndicacao = $primeiroPagamentoIndicacao;
        $this->descontoBoasVindas = $descontoBoasVindas ?: new DescontoBoasVindasService($this->cobrancas);
        $this->notificacoesFinanceiras = $notificacoesFinanceiras;
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
            $integracao = $this->integrarCobrancaAsaas($clienteId, (int) $pendente['COB_ID'], $plano, $ciclo);
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
                'valor'=>$valor, 'vencimento'=>date('Y-m-d', strtotime('+3 days')), 'vencimento_efetivo'=>date('Y-m-d', strtotime('+3 days')), 'tipo'=>'mensalidade',
                'provider'=>'asaas', 'provider_status'=>'local_pendente'
            ]);
        });

        $integracao = $this->integrarCobrancaAsaas($clienteId, (int) $cobrancaId, $plano, $ciclo);
        $this->log('contratacao', ['cliente_id'=>$clienteId, 'cobranca_id'=>$cobrancaId, 'sucesso'=>$integracao['sucesso']]);
        return array_merge($integracao, ['cobranca_id'=>(int) $cobrancaId, 'plano'=>$plano]);
    }

    public function ofertasParaContratacao(int $clienteId, array $planos, ?array $cobrancaPendente = null): array
    {
        $ofertas = [];
        $cobrancaPendenteId = !empty($cobrancaPendente['COB_ID']) ? (int) $cobrancaPendente['COB_ID'] : null;
        $elegivel = $this->descontoBoasVindas->clienteElegivel($clienteId, $cobrancaPendenteId);

        foreach($planos as $plano){
            $planoId = (int) ($plano['PLA_ID'] ?? 0);
            foreach(array_keys(Plano::CICLOS) as $ciclo){
                $ofertaBoasVindas = $this->descontoBoasVindas->calcular($plano, $ciclo);
                $valorBaseCentavos = $ofertaBoasVindas['valor_base_centavos'];
                $descontoInicialCentavos = 0;
                $cobrancaPendenteCorresponde = !empty($cobrancaPendente)
                    && (int) ($cobrancaPendente['PLA_ID'] ?? 0) === $planoId
                    && (string) ($cobrancaPendente['COB_Ciclo'] ?? '') === $ciclo;

                if($elegivel && (empty($cobrancaPendente) || $cobrancaPendenteCorresponde)){
                    $descontoInicialCentavos = $ofertaBoasVindas['desconto_centavos'];
                }

                if($cobrancaPendenteCorresponde && array_key_exists('COB_DescontoInicialCentavos', $cobrancaPendente)){
                    if(isset($cobrancaPendente['COB_ValorBaseCentavos'])){
                        $valorBaseCentavos = max(0, (int) $cobrancaPendente['COB_ValorBaseCentavos']);
                    }
                    $descontoInicialCentavos = max(0, (int) ($cobrancaPendente['COB_DescontoInicialCentavos'] ?? 0));
                }

                $ofertas[$planoId][$ciclo] = [
                    'elegivel'=>$descontoInicialCentavos > 0,
                    'valor_normal_centavos'=>$valorBaseCentavos,
                    'desconto_inicial_centavos'=>$descontoInicialCentavos,
                    'primeiro_pagamento_centavos'=>max(0, $valorBaseCentavos - $descontoInicialCentavos)
                ];
            }
        }

        return $ofertas;
    }

    public function confirmarPagamentoManual(int $cobrancaId, array $dados = []): array
    {
        $cobranca = $this->cobrancas->buscar($cobrancaId);
        if(!$cobranca || !in_array(strtolower((string) $cobranca['COB_Status']), ['pendente','vencido'], true)){
            throw new \DomainException('Não foi possível lançar o pagamento.');
        }
        $situacaoAnterior = $this->servicoNotificacoesFinanceiras()->situacaoAntesPagamento((int)$cobranca['CLI_ID']);
        $valorPagoCentavos = $this->valorManualEmCentavos($dados['valor_pago'] ?? null);
        $temDescontoIndicacao = (int) ($cobranca['COB_DescontoIndicacaoCentavos'] ?? 0) > 0;
        $decisaoIndicacao = trim((string) ($dados['decisao_indicacao'] ?? ''));
        if($temDescontoIndicacao && !in_array($decisaoIndicacao, ['aplicado', 'nao_aplicado'], true)){
            throw new \DomainException('Informe se o desconto de indicação foi aplicado.');
        }
        if(!$temDescontoIndicacao){ $decisaoIndicacao = null; }

        $composicao = $this->composicaoCobranca($cobranca);
        $valorEsperadoCentavos = $decisaoIndicacao === 'nao_aplicado'
            ? $composicao['sem_indicacao_centavos']
            : $composicao['com_indicacao_centavos'];
        $divergente = $valorPagoCentavos !== $valorEsperadoCentavos;
        if($divergente && empty($dados['confirmar_valor_divergente'])){
            throw new \DomainException('Confirme que o valor informado diverge do valor esperado antes de lançar o pagamento.');
        }
        $motivo = trim((string) ($dados['motivo'] ?? ''));
        if(mb_strlen($motivo, 'UTF-8') > 500){ throw new \DomainException('A observação deve ter no máximo 500 caracteres.'); }
        $usuarioId = (int) ($dados['usuario_id'] ?? 0) ?: null;

        $this->transacao->executar(function() use ($cobrancaId, $cobranca, $valorPagoCentavos, $decisaoIndicacao, $motivo, $usuarioId, $divergente, $valorEsperadoCentavos, $situacaoAnterior){
            $atualizada = $this->cobrancas->buscarParaAtualizacao($cobrancaId);
            if(!$atualizada || !in_array(strtolower((string) $atualizada['COB_Status']), ['pendente','vencido'], true)){
                throw new \DomainException('Não foi possível lançar o pagamento.');
            }
            if($decisaoIndicacao === 'aplicado'){
                $this->servicoDescontosIndicacao()->confirmarUtilizacao('cobranca', (string) $cobrancaId);
            }elseif($decisaoIndicacao === 'nao_aplicado'){
                $this->liberarDescontoIndicacao($atualizada, $motivo !== '' ? $motivo : 'pagamento_manual_sem_desconto_indicacao');
            }
            if(!$this->cobrancas->registrarPagamentoManual($cobrancaId, [
                'valor_pago_centavos'=>$valorPagoCentavos,
                'decisao_indicacao'=>$decisaoIndicacao,
                'motivo'=>$motivo,
                'usuario_id'=>$usuarioId,
                'valor_esperado_centavos'=>$valorEsperadoCentavos,
                'valor_divergente'=>$divergente
            ])){
                throw new \RuntimeException('Não foi possível registrar a auditoria do pagamento manual.');
            }
            if($decisaoIndicacao !== null && class_exists(IndicacaoAuditoriaService::class)){
                $this->servicoAuditoriaIndicacao()->registrar('cobranca', $cobrancaId, 'pagamento_manual_confirmado', null, 'pago', $motivo ?: null, $usuarioId, 'manual:' . $cobrancaId, [
                    'origem'=>'manual', 'valor_pago_centavos'=>$valorPagoCentavos, 'valor_esperado_centavos'=>$valorEsperadoCentavos,
                    'decisao_indicacao'=>$decisaoIndicacao, 'valor_divergente'=>$divergente ? 'sim' : 'nao'
                ]);
            }
            if(!$this->cobrancas->marcarPago($cobrancaId)){
                throw new \RuntimeException('Não foi possível lançar o pagamento.');
            }
            $this->ativarAssinaturaDaCobranca($cobranca);
            $this->clientes->atualizarEstadoFinanceiro($cobranca['CLI_ID'], ['status_pagamento'=>'pago', 'status_cadastro'=>'ativo', 'liberar_se_vazio'=>true]);
            $this->processarIndicacaoNoPrimeiroPagamento($cobranca, new \DateTimeImmutable('now'));
            $this->servicoNotificacoesFinanceiras()->agendarPagamentoConfirmado($cobrancaId, $situacaoAnterior);
        });
        $this->log('pagamento_manual', ['cobranca_id'=>$cobrancaId, 'origem'=>'manual', 'valor_pago_centavos'=>$valorPagoCentavos, 'decisao_indicacao'=>$decisaoIndicacao, 'valor_divergente'=>$divergente, 'usuario_id'=>$usuarioId]);
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
        $situacaoAnterior = $status === 'pago' ? $this->servicoNotificacoesFinanceiras()->situacaoAntesPagamento((int)$cobranca['CLI_ID']) : 'regular';

        return $this->transacao->executar(function() use ($cobranca, $eventId, $evento, $providerStatus, $payload, $payment, $status, $situacaoAnterior){
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
                $this->reconciliarDescontoIndicacaoNoPagamento($atualizada, $payment);
                $this->ativarAssinaturaDaCobranca($cobranca);
                $this->clientes->atualizarEstadoFinanceiro($cobranca['CLI_ID'], ['status_pagamento'=>'pago','status_cadastro'=>'ativo','ativo'=>'S']);
                $this->processarIndicacaoNoPrimeiroPagamento($cobranca, new \DateTimeImmutable('now'));
                $this->servicoNotificacoesFinanceiras()->agendarPagamentoConfirmado((int)$cobranca['COB_ID'], $situacaoAnterior);
            }elseif($status === 'vencido'){
                $this->clientes->atualizarEstadoFinanceiro($cobranca['CLI_ID'], ['status_pagamento'=>'pendente']);
            }elseif($status === 'cancelado' && strtolower((string) $atualizada['COB_Status']) !== 'pago'){
                $this->liberarDescontoIndicacao($atualizada, 'cobranca_cancelada_provider');
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
        foreach($this->assinaturas->listarParaRecorrencia($this->recorrencia->diasAntecedencia()) as $assinatura){
            $resultado['assinaturas_processadas']++;
            $vencimento = $assinatura['ASS_DataProximaCobranca'] ?: date('Y-m-d');
            $existente = $this->cobrancas->buscarPorCompetencia($assinatura['ASS_ID'], $vencimento, 'mensalidade');
            if($existente && $existente['COB_Status'] !== 'cancelado' && !empty($existente['COB_ProviderPaymentId'])){
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
                        'valor'=>$assinatura['ASS_Valor'], 'vencimento'=>$vencimento, 'vencimento_efetivo'=>$vencimento, 'tipo'=>'mensalidade',
                        'provider'=>'asaas', 'provider_status'=>'local_pendente'
                    ]);
                });
                $cobrancaId = $reserva['id'];
                $plano = $this->planos->buscar($assinatura['PLA_ID']);
                $integracao = $this->integrarCobrancaAsaas($assinatura['CLI_ID'], (int) $cobrancaId, $plano ?: [], $assinatura['ASS_Ciclo']);
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
            foreach($this->cobrancas->listarPendentesPorCliente($clienteId) as $cobranca){
                $this->liberarDescontoIndicacao($cobranca, 'contrato_cancelado');
            }
            $this->assinaturas->cancelarVigentesPorCliente($clienteId);
            $this->cobrancas->cancelarPendentesPorCliente($clienteId);
            $this->clientes->atualizarEstadoFinanceiro($clienteId, ['status_pagamento'=>'pendente', 'status_cadastro'=>'suspenso']);
        });
        $this->log('cancelamento_contrato', ['cliente_id'=>$clienteId, 'motivo'=>$motivo]);
        return ['sucesso'=>true];
    }

    public function cancelarCobranca(int $cobrancaId, string $motivo = 'cancelamento_manual'): array
    {
        $this->transacao->executar(function() use ($cobrancaId, $motivo){
            $cobranca = $this->cobrancas->buscarParaAtualizacao($cobrancaId);
            if(!$cobranca){ throw new \DomainException('Cobrança não encontrada.'); }
            if(($cobranca['COB_Status'] ?? '') === 'cancelado'){ return; }
            if(($cobranca['COB_Status'] ?? '') === 'pago'){ throw new \DomainException('Cobrança paga não pode ser cancelada por este fluxo.'); }
            $this->liberarDescontoIndicacao($cobranca, $motivo);
            $this->cobrancas->cancelar($cobrancaId);
        });
        $this->log('cancelamento_cobranca', ['cobranca_id'=>$cobrancaId, 'motivo'=>$motivo]);
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
            $this->garantirReativacaoPendente($clienteId, $pendente, $plano, $ciclo, $valor);
            $integracao = $this->integrarCobrancaAsaas($clienteId, (int) $pendente['COB_ID'], $plano, $ciclo);
            return array_merge($integracao, ['cobranca_id'=>(int) $pendente['COB_ID']]);
        }
        $cobrancaId = $this->transacao->executar(function() use ($clienteId, $assinatura, $plano, $ciclo, $valor){
            $this->cobrancas->cancelarPendentesPorCliente($clienteId);
            $this->assinaturas->criarOuAtualizarPorCliente($clienteId, $plano, 'pendente', ['ciclo'=>$ciclo,'valor'=>$valor,'proxima_cobranca'=>date('Y-m-d', strtotime('+' . Plano::mesesPorCiclo($ciclo) . ' months'))]);
            $atual = $this->assinaturas->buscarParaPagamento($clienteId, $plano['PLA_ID']);
            $this->clientes->atualizarEstadoFinanceiro($clienteId, ['ativo'=>'S','status_cadastro'=>'suspenso','status_pagamento'=>'pendente','plano'=>$plano['PLA_ID']]);
            $vencimento = date('Y-m-d', strtotime('+3 days'));
            return $this->cobrancas->criar(['cliente'=>$clienteId,'plano'=>$plano['PLA_ID'],'assinatura'=>$atual['ASS_ID'] ?? null,'valor'=>$valor,'vencimento'=>$vencimento,'vencimento_efetivo'=>$vencimento,'tipo'=>'mensalidade','provider'=>'asaas','provider_status'=>'local_pendente']);
        });
        $integracao = $this->integrarCobrancaAsaas($clienteId, (int) $cobrancaId, $plano, $ciclo);
        $this->log('reativacao_contrato', ['cliente_id'=>$clienteId,'cobranca_id'=>$cobrancaId,'integrada'=>$integracao['sucesso']]);
        return array_merge($integracao, ['cobranca_id'=>(int) $cobrancaId]);
    }

    public function recuperarIntegracaoCobranca(int $clienteId, int $cobrancaId): array
    {
        $cobranca = $this->cobrancas->buscar($cobrancaId);
        if(!$cobranca || (int) ($cobranca['CLI_ID'] ?? 0) !== $clienteId){
            throw new \DomainException('Cobrança não disponível para este cliente.');
        }
        if(!in_array((string) ($cobranca['COB_Status'] ?? ''), ['pendente','vencido'], true)){
            throw new \DomainException('Cobrança não está aberta.');
        }
        $assinatura = $this->assinaturas->buscarParaRegularizacaoFinanceira($clienteId);
        if(!$assinatura || (int) ($cobranca['ASS_ID'] ?? 0) !== (int) $assinatura['ASS_ID']){
            throw new \DomainException('Cobrança não pertence ao contexto financeiro atual.');
        }
        $plano = $this->planos->buscar((int) $cobranca['PLA_ID']);
        if(!$plano){ throw new \DomainException('Plano da cobrança não está disponível.'); }
        $ciclo = (string) ($cobranca['COB_Ciclo'] ?? ($assinatura['ASS_Ciclo'] ?? $plano['PLA_Periodicidade']));
        $resultado = $this->integrarCobrancaAsaas($clienteId, $cobrancaId, $plano, $ciclo);
        $this->log('recuperacao_cobranca_cliente', ['cliente_id'=>$clienteId,'cobranca_id'=>$cobrancaId,'sucesso'=>!empty($resultado['sucesso']),'reconciliada'=>!empty($resultado['reconciliada'])]);
        if(!empty($resultado['sucesso'])){
            $resultado['mensagem'] = 'Link de pagamento disponível. Você já pode continuar a regularização.';
        }else{
            $resultado['mensagem'] = 'Não foi possível gerar o link de pagamento. Tente novamente ou entre em contato com o suporte.';
        }
        return $resultado;
    }

    public function cancelarAssinatura(int $assinaturaId, ?string $motivo = null): array
    {
        $assinatura = $this->assinaturas->buscarPorId($assinaturaId);
        if(!$assinatura){ throw new \DomainException('Assinatura não encontrada.'); }
        $this->assinaturas->cancelar($assinaturaId);
        $this->log('cancelamento_assinatura', ['assinatura_id'=>$assinaturaId, 'motivo'=>$motivo]);
        return ['sucesso'=>true];
    }

    private function integrarCobrancaAsaas(int $clienteId, int $cobrancaId, array $plano, string $ciclo): array
    {
        return $this->cobrancas->comLockIntegracao($cobrancaId, function() use ($clienteId, $cobrancaId, $plano, $ciclo){
            $cliente = $this->clientes->buscar($clienteId);
            $cobranca = $this->cobrancas->buscar($cobrancaId);
            if(!$cliente || !$cobranca){ throw new \RuntimeException('Cliente ou cobrança não encontrada.'); }
            $referencia = $this->referenciaExternaAtual($cobranca);
            if(($cobranca['COB_Status'] ?? '') === 'cancelado'){
                $reprocessamento = $this->prepararCobrancaCanceladaParaRetry($cobranca, $referencia);
                if(!$reprocessamento['sucesso']){ return $reprocessamento; }
                $referencia = $reprocessamento['referencia'];
                $cobranca = $this->cobrancas->buscar($cobrancaId);
            }
            if(!empty($cobranca['COB_ProviderPaymentId']) && $this->linkPagamentoValido($cobranca['COB_LinkPagamento'] ?? null)){
                return ['sucesso'=>true,'reconciliada'=>true,'mensagem'=>'Cobrança já integrada.'];
            }
            $cobranca = $this->prepararDescontosDaCobranca($clienteId, $cobranca, $plano, $ciclo);
            $vencimentoRecuperacao = $this->recorrencia->vencimentoEfetivoGateway($cobranca['COB_DataVencimento']);

            $pagamento = null;
            $providerPaymentId = trim((string) ($cobranca['COB_ProviderPaymentId'] ?? ''));
            if($providerPaymentId !== ''){
                $consultaPorId = $this->asaas->consultarCobranca($providerPaymentId);
                if(!empty($consultaPorId['sucesso']) && !empty($consultaPorId['response']['id'])){
                    $pagamento = $consultaPorId['response'];
                }elseif((int) ($consultaPorId['http_code'] ?? 0) !== 404){
                    $this->registrarFalhaGateway($cobrancaId, 'erro_reconciliacao', $consultaPorId, $referencia);
                    return ['sucesso'=>false,'mensagem'=>'Não foi possível confirmar a situação da cobrança. Tente novamente em instantes.'];
                }
            }

            $customerId = trim((string) ($cliente['CLI_ProviderCustomerId'] ?? ''));
            if($customerId === ''){
                $respostaCliente = $this->asaas->criarOuAtualizarCliente($cliente);
                if(empty($respostaCliente['sucesso']) || empty($respostaCliente['response']['id'])){
                    $this->registrarFalhaGateway($cobrancaId, 'erro_cliente', $respostaCliente, $referencia);
                    $this->liberarDescontoIndicacao($cobranca, 'falha_criacao_cliente_asaas');
                    return ['sucesso'=>false,'mensagem'=>'Não foi possível gerar a cobrança automaticamente. Verifique os dados cadastrais ou entre em contato com o suporte.'];
                }
                $customerId = $respostaCliente['response']['id'];
                $this->clientes->atualizarProviderPagamento($clienteId, 'asaas', $customerId);
                $cliente['CLI_ProviderCustomerId'] = $customerId;
            }

            $consulta = ['sucesso'=>true,'response'=>['data'=>[]]];
            if(!$pagamento){
                $consulta = $this->asaas->buscarCobrancaPorReferenciaExterna($referencia);
                if(empty($consulta['sucesso'])){
                    $this->registrarFalhaGateway($cobrancaId, 'erro_reconciliacao', $consulta, $referencia, $customerId);
                    return ['sucesso'=>false,'mensagem'=>'Não foi possível confirmar a situação da cobrança no Asaas. Tente novamente em instantes.'];
                }
                $pagamento = $this->primeiraCobrancaEncontrada($consulta);
            }
            while($pagamento && $this->pagamentoExternoCancelado($pagamento)){
                $tentativa = $this->numeroTentativa($referencia) + 1;
                $referencia = 'cobranca_' . $cobrancaId . '_tentativa_' . $tentativa;
                $this->cobrancas->prepararReprocessamento($cobrancaId, $tentativa);
                $consulta = $this->asaas->buscarCobrancaPorReferenciaExterna($referencia);
                if(empty($consulta['sucesso'])){
                    $this->registrarFalhaGateway($cobrancaId, 'erro_reconciliacao', $consulta, $referencia, $customerId);
                    return ['sucesso'=>false,'mensagem'=>'Não foi possível confirmar a situação da cobrança no Asaas. Tente novamente em instantes.'];
                }
                $pagamento = $this->primeiraCobrancaEncontrada($consulta);
            }
            $reconciliada = (bool) $pagamento;
            if(!$pagamento){
                $this->persistirVencimentoEfetivo($cobrancaId, $vencimentoRecuperacao);
                $cobranca['COB_DataVencimentoEfetivo'] = $vencimentoRecuperacao;
                $cobranca['descricao'] = 'Mensalidade ' . ($plano['PLA_Nome'] ?? 'Disparador.net');
                $resposta = $this->asaas->criarCobranca($cliente, $cobranca, $referencia);
                if(empty($resposta['sucesso']) || empty($resposta['response']['id'])){
                    $this->registrarFalhaGateway($cobrancaId, 'erro_cobranca', $resposta, $referencia, $customerId);
                    $this->liberarDescontoIndicacao($cobranca, 'falha_criacao_cobranca_asaas');
                    return ['sucesso'=>false,'mensagem'=>'Plano selecionado, mas o Asaas não retornou o link de pagamento. Tente novamente em instantes ou fale com o suporte.'];
                }
                $pagamento = $resposta['response'];
            }else{
                $vencimentoEfetivo = $this->vencimentoEfetivoReconciliado($pagamento, $cobranca, $vencimentoRecuperacao);
                $this->persistirVencimentoEfetivo($cobrancaId, $vencimentoEfetivo);
                $cobranca['COB_DataVencimentoEfetivo'] = $vencimentoEfetivo;
            }

            $pix = $this->asaas->buscarPixQrCode($pagamento['id']);
            $pixDados = !empty($pix['sucesso']) && is_array($pix['response']) ? $pix['response'] : [];
            $this->cobrancas->atualizarIntegracaoProvider($cobrancaId, ['provider'=>'asaas','provider_customer_id'=>$customerId,'provider_payment_id'=>$pagamento['id'],'provider_status'=>$pagamento['status'] ?? null,'provider_payload'=>$this->payloadProviderSeguro($pagamento),'link_pagamento'=>$pagamento['invoiceUrl'] ?? ($pagamento['bankSlipUrl'] ?? null),'pix_copia_cola'=>$pixDados['payload'] ?? null,'qr_code'=>$pixDados['encodedImage'] ?? null,'linha_digitavel'=>$pagamento['identificationField'] ?? null,'status'=>'pendente']);
            return ['sucesso'=>true,'reconciliada'=>$reconciliada,'mensagem'=>'Plano selecionado. A cobrança foi criada e o link de pagamento está disponível.'];
        });
    }

    private function prepararDescontosDaCobranca(int $clienteId, array $cobranca, array $plano, string $ciclo): array
    {
        if(array_key_exists('COB_ValorBaseCentavos', $cobranca) && $cobranca['COB_ValorBaseCentavos'] !== null){
            if((int) ($cobranca['COB_DescontoIndicacaoCentavos'] ?? 0) > 0){
                $this->servicoDescontosIndicacao()->garantirReservasDaReferencia(
                    'cobranca',
                    (string) $cobranca['COB_ID'],
                    (int) $cobranca['COB_DescontoIndicacaoCentavos']
                );
            }
            return $cobranca;
        }

        $valorBaseCentavos = $this->valorEmCentavos($cobranca['COB_Valor']);
        $primeiraCobranca = $this->descontoBoasVindas->clienteElegivel($clienteId, (int) $cobranca['COB_ID']);
        $descontoInicialCentavos = $primeiraCobranca
            ? $this->descontoBoasVindas->calcular($plano, $ciclo, $valorBaseCentavos)['desconto_centavos']
            : 0;
        $descontoIndicacaoCentavos = 0;

        if(!$primeiraCobranca){
            $resultado = $this->servicoDescontosIndicacao()->prepararDesconto(
                $clienteId,
                $ciclo,
                $valorBaseCentavos,
                'cobranca',
                (string) $cobranca['COB_ID']
            );
            $descontoIndicacaoCentavos = (int) ($resultado['desconto_total_centavos'] ?? 0);
        }

        $valorFinalCentavos = max(0, $valorBaseCentavos - $descontoInicialCentavos - $descontoIndicacaoCentavos);
        $this->cobrancas->registrarComposicaoDesconto((int) $cobranca['COB_ID'], [
            'valor_base_centavos'=>$valorBaseCentavos,
            'desconto_inicial_centavos'=>$descontoInicialCentavos,
            'desconto_indicacao_centavos'=>$descontoIndicacaoCentavos,
            'adicionais_centavos'=>0,
            'ciclo'=>$ciclo,
            'valor'=>$this->centavosEmValor($valorFinalCentavos)
        ]);

        return $this->cobrancas->buscar((int) $cobranca['COB_ID']);
    }

    private function reconciliarDescontoIndicacaoNoPagamento(array $cobranca, array $payment): void
    {
        if((int) ($cobranca['COB_DescontoIndicacaoCentavos'] ?? 0) <= 0){ return; }
        $decisao = $this->decidirUsoDescontoIndicacao($cobranca, $payment);
        if($decisao === 'utilizar'){
            $this->servicoDescontosIndicacao()->confirmarUtilizacao('cobranca', (string) $cobranca['COB_ID']);
            return;
        }
        if($decisao === 'liberar'){
            $this->liberarDescontoIndicacao($cobranca, 'pagamento_sem_desconto_indicacao');
        }
    }

    private function decidirUsoDescontoIndicacao(array $cobranca, array $payment): string
    {
        if(!array_key_exists('COB_ValorBaseCentavos', $cobranca) || $cobranca['COB_ValorBaseCentavos'] === null){
            return 'indeterminado';
        }

        $valorPago = $this->valorProviderEmCentavos($payment['value'] ?? null);
        if($valorPago === null){ return 'indeterminado'; }

        $valorNominal = max(0, (int) $cobranca['COB_ValorBaseCentavos'])
            + max(0, (int) ($cobranca['COB_AdicionaisCentavos'] ?? 0));
        $descontoInicial = max(0, (int) ($cobranca['COB_DescontoInicialCentavos'] ?? 0));
        $descontoIndicacao = max(0, (int) ($cobranca['COB_DescontoIndicacaoCentavos'] ?? 0));
        $valorComIndicacao = max(0, $valorNominal - $descontoInicial - $descontoIndicacao);
        $valorSemIndicacao = max(0, $valorNominal - $descontoInicial);
        $valorOriginal = array_key_exists('originalValue', $payment) && $payment['originalValue'] !== null
            ? $this->valorProviderEmCentavos($payment['originalValue'])
            : null;

        // Asaas informa originalValue quando o valor efetivamente pago diverge do
        // original. Sem esse par, não é seguro atribuir o desconto à indicação.
        if($valorPago === $valorComIndicacao && $valorOriginal === $valorNominal){
            return 'utilizar';
        }
        if($valorPago === $valorSemIndicacao && ($valorOriginal === null || $valorOriginal === $valorNominal)){
            return 'liberar';
        }
        return 'indeterminado';
    }

    private function liberarDescontoIndicacao(array $cobranca, string $motivo): void
    {
        if((int) ($cobranca['COB_DescontoIndicacaoCentavos'] ?? 0) <= 0){ return; }
        $this->servicoDescontosIndicacao()->liberarReservas('cobranca', (string) $cobranca['COB_ID'], $motivo);
    }

    private function servicoDescontosIndicacao()
    {
        if(!$this->descontosIndicacao){ $this->descontosIndicacao = new IndicacaoDescontoService(); }
        return $this->descontosIndicacao;
    }

    private function servicoNotificacoesFinanceiras()
    {
        if(!$this->notificacoesFinanceiras){
            $this->notificacoesFinanceiras = new FinanceiroNotificacaoService($this->cobrancas, $this->assinaturas, $this->clientes, $this->planos);
        }
        return $this->notificacoesFinanceiras;
    }

    private function valorEmCentavos($valor): int
    {
        $texto = str_replace(',', '.', trim((string) $valor));
        if(!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $texto, $partes)){
            throw new \DomainException('Valor inválido para cobrança.');
        }
        return ((int) $partes[1] * 100) + (int) str_pad($partes[2] ?? '', 2, '0');
    }

    private function servicoAuditoriaIndicacao()
    {
        if(!$this->auditoriaIndicacao){ $this->auditoriaIndicacao = new IndicacaoAuditoriaService(); }
        return $this->auditoriaIndicacao;
    }

    private function processarIndicacaoNoPrimeiroPagamento(array $cobranca, \DateTimeInterface $pagoEm): void
    {
        $clienteId = (int) ($cobranca['CLI_ID'] ?? 0);
        if($clienteId <= 0 || $this->cobrancas->contarPagasPorCliente($clienteId) !== 1){
            return;
        }

        if(!$this->primeiroPagamentoIndicacao){
            $this->primeiroPagamentoIndicacao = new IndicacaoPrimeiroPagamentoService();
        }
        $this->primeiroPagamentoIndicacao->processar($clienteId, $pagoEm);
    }

    private function valorManualEmCentavos($valor): int
    {
        if($valor === null || trim((string) $valor) === ''){ throw new \DomainException('Informe o valor efetivamente pago.'); }
        try{ $centavos = $this->valorEmCentavos($valor); }
        catch(\DomainException $e){ throw new \DomainException('Informe um valor pago válido.'); }
        if($centavos <= 0){ throw new \DomainException('Informe um valor pago maior que zero.'); }
        return $centavos;
    }

    private function composicaoCobranca(array $cobranca): array
    {
        $nominal = array_key_exists('COB_ValorBaseCentavos', $cobranca) && $cobranca['COB_ValorBaseCentavos'] !== null
            ? max(0, (int) $cobranca['COB_ValorBaseCentavos']) + max(0, (int) ($cobranca['COB_AdicionaisCentavos'] ?? 0))
            : $this->valorEmCentavos($cobranca['COB_Valor']);
        $inicial = max(0, (int) ($cobranca['COB_DescontoInicialCentavos'] ?? 0));
        $indicacao = max(0, (int) ($cobranca['COB_DescontoIndicacaoCentavos'] ?? 0));
        return ['nominal_centavos'=>$nominal, 'desconto_inicial_centavos'=>$inicial, 'desconto_indicacao_centavos'=>$indicacao,
            'com_indicacao_centavos'=>max(0, $nominal - $inicial - $indicacao), 'sem_indicacao_centavos'=>max(0, $nominal - $inicial)];
    }

    private function valorProviderEmCentavos($valor): ?int
    {
        if($valor === null || $valor === ''){ return null; }
        if(is_int($valor)){ return $valor * 100; }
        if(is_float($valor)){ $valor = number_format($valor, 2, '.', ''); }
        try{
            return $this->valorEmCentavos($valor);
        }catch(\DomainException $e){
            return null;
        }
    }

    private function centavosEmValor(int $centavos): string
    {
        return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    private function primeiraCobrancaEncontrada(array $resposta): ?array
    {
        if(empty($resposta['sucesso']) || !is_array($resposta['response'] ?? null)){
            return null;
        }
        $dados = $resposta['response']['data'] ?? [];
        return is_array($dados) && isset($dados[0]) && is_array($dados[0]) ? $dados[0] : null;
    }

    private function garantirReativacaoPendente(int $clienteId, array $cobranca, array $plano, string $ciclo, $valor): void
    {
        $this->transacao->executar(function() use ($clienteId, $cobranca, $plano, $ciclo, $valor){
            $bloqueada = $this->cobrancas->buscarParaAtualizacao($cobranca['COB_ID']);
            if(!$bloqueada){ throw new \RuntimeException('Cobrança pendente não encontrada.'); }
            $assinatura = !empty($bloqueada['ASS_ID']) ? $this->assinaturas->buscarPorId($bloqueada['ASS_ID']) : null;
            $compativel = $assinatura
                && (int) $assinatura['CLI_ID'] === $clienteId
                && (int) $assinatura['PLA_ID'] === (int) $plano['PLA_ID']
                && $assinatura['ASS_Status'] === 'pendente';

            if(!$compativel){
                $this->assinaturas->criarOuAtualizarPorCliente($clienteId, $plano, 'pendente', [
                    'ciclo'=>$ciclo,
                    'valor'=>$valor,
                    'proxima_cobranca'=>date('Y-m-d', strtotime('+' . Plano::mesesPorCiclo($ciclo) . ' months'))
                ]);
                $assinatura = $this->assinaturas->buscarParaPagamento($clienteId, $plano['PLA_ID']);
            }
            if(!$assinatura || $assinatura['ASS_Status'] !== 'pendente'){
                throw new \LogicException('Não foi possível garantir uma assinatura pendente para a reativação.');
            }
            if((int) ($bloqueada['ASS_ID'] ?? 0) !== (int) $assinatura['ASS_ID']){
                if(!$this->cobrancas->vincularAssinatura($bloqueada['COB_ID'], $assinatura['ASS_ID'])){
                    throw new \RuntimeException('Não foi possível vincular a cobrança à assinatura pendente.');
                }
            }
            $this->clientes->atualizarEstadoFinanceiro($clienteId, ['ativo'=>'S','status_cadastro'=>'suspenso','status_pagamento'=>'pendente','plano'=>$plano['PLA_ID']]);
        });
    }

    private function prepararCobrancaCanceladaParaRetry(array $cobranca, string $referenciaAtual): array
    {
        $paymentId = trim((string) ($cobranca['COB_ProviderPaymentId'] ?? ''));
        if($paymentId === ''){
            $this->cobrancas->prepararReprocessamento($cobranca['COB_ID'], $this->numeroTentativa($referenciaAtual));
            return ['sucesso'=>true,'referencia'=>$referenciaAtual];
        }

        $consulta = $this->asaas->consultarCobranca($paymentId);
        if(!empty($consulta['sucesso']) && is_array($consulta['response'] ?? null)){
            $status = strtoupper((string) ($consulta['response']['status'] ?? ''));
            if(!$this->pagamentoExternoCancelado($consulta['response'])){
                $this->cobrancas->atualizarIntegracaoProvider($cobranca['COB_ID'], ['status'=>'pendente','provider_status'=>$status]);
                return ['sucesso'=>true,'referencia'=>$referenciaAtual];
            }
        }elseif((int) ($consulta['http_code'] ?? 0) !== 404){
            return ['sucesso'=>false,'mensagem'=>'Não foi possível consultar a cobrança cancelada no Asaas.'];
        }

        $tentativa = $this->numeroTentativa($referenciaAtual) + 1;
        $novaReferencia = 'cobranca_' . $cobranca['COB_ID'] . '_tentativa_' . $tentativa;
        $this->cobrancas->prepararReprocessamento($cobranca['COB_ID'], $tentativa);
        return ['sucesso'=>true,'referencia'=>$novaReferencia];
    }

    private function referenciaExternaAtual(array $cobranca): string
    {
        $base = 'cobranca_' . $cobranca['COB_ID'];
        if(($cobranca['COB_ProviderStatus'] ?? '') === 'reprocessamento_base'){ return $base; }
        if(preg_match('/^reprocessamento_tentativa_(\d+)$/', (string) ($cobranca['COB_ProviderStatus'] ?? ''), $match)){
            return $base . '_tentativa_' . (int) $match[1];
        }
        $payload = json_decode((string) ($cobranca['COB_ProviderPayload'] ?? ''), true);
        $referencia = $payload['payment']['externalReference'] ?? null;
        return is_string($referencia) && $referencia !== '' ? $referencia : $base;
    }

    private function numeroTentativa(string $referencia): int
    {
        return preg_match('/_tentativa_(\d+)$/', $referencia, $match) ? max(2, (int) $match[1]) : 1;
    }

    private function pagamentoExternoCancelado(array $pagamento): bool
    {
        return in_array(strtoupper((string) ($pagamento['status'] ?? '')), ['DELETED','REFUNDED','CANCELLED'], true);
    }

    private function linkPagamentoValido($link): bool
    {
        $partes = parse_url(trim((string) $link));
        return is_array($partes)
            && in_array(strtolower((string) ($partes['scheme'] ?? '')), ['http','https'], true)
            && !empty($partes['host'])
            && (bool) filter_var($link, FILTER_VALIDATE_URL);
    }

    private function reconciliarProximaCobranca(array $assinatura, string $cicloProcessado): void
    {
        $proxima = $this->recorrencia->calcularProximaData($assinatura['ASS_Ciclo'], $cicloProcessado);
        $this->assinaturas->avancarProximaCobrancaSeCiclo($assinatura['ASS_ID'], $cicloProcessado, $proxima);
    }

    private function ativarAssinaturaDaCobranca(array $cobranca): void
    {
        $id = (int) ($cobranca['ASS_ID'] ?? 0);
        $assinatura = $id > 0 ? $this->assinaturas->buscarPorId($id) : null;
        $valida = $assinatura
            && (int) $assinatura['CLI_ID'] === (int) $cobranca['CLI_ID']
            && (int) $assinatura['PLA_ID'] === (int) $cobranca['PLA_ID']
            && in_array($assinatura['ASS_Status'], ['pendente','ativa','vencida'], true);
        if(!$valida){
            $assinatura = $this->assinaturas->buscarPendenteMaisRecente($cobranca['CLI_ID'], $cobranca['PLA_ID']);
            if(!$assinatura){
                $this->log('erro_consistencia_pagamento', ['cobranca_id'=>$cobranca['COB_ID'], 'motivo'=>'assinatura_valida_ausente']);
                throw new \LogicException('Cobrança sem assinatura válida para ativação.');
            }
            $id = (int) $assinatura['ASS_ID'];
            if(!$this->cobrancas->vincularAssinatura($cobranca['COB_ID'], $id)){
                throw new \RuntimeException('Não foi possível vincular a cobrança à assinatura válida.');
            }
        }
        if(!$this->assinaturas->ativar($id)){
            throw new \RuntimeException('Não foi possível ativar a assinatura da cobrança.');
        }
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
        return json_encode(['event'=>$payload['event'] ?? null,'id'=>$payload['id'] ?? null,'decisao'=>$decisao,'payment'=>array_intersect_key($payment, array_flip(['id','customer','status','value','originalValue','netValue','billingType','dueDate','paymentDate','clientPaymentDate','confirmedDate','invoiceUrl','bankSlipUrl','externalReference']))], JSON_UNESCAPED_UNICODE);
    }

    private function registrarFalhaGateway(int $cobrancaId, string $status, array $resultado, string $referencia, ?string $customerId = null): void
    {
        $diagnostico = [
            'sucesso'=>(bool) ($resultado['sucesso'] ?? false),
            'http_code'=>(int) ($resultado['http_code'] ?? 0),
            'endpoint'=>mb_substr((string) ($resultado['endpoint'] ?? ''), 0, 255),
            'method'=>mb_substr((string) ($resultado['method'] ?? ''), 0, 12),
            'erro'=>$this->sanitizarTextoGateway($resultado['erro'] ?? ''),
            'externalReference'=>$referencia,
            'response'=>$this->sanitizarDadosGateway(is_array($resultado['response'] ?? null) ? $resultado['response'] : [])
        ];
        $dados = ['provider'=>'asaas','provider_status'=>$status,'provider_payload'=>json_encode($diagnostico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        if($customerId !== null){ $dados['provider_customer_id'] = $customerId; }
        $this->cobrancas->atualizarIntegracaoProvider($cobrancaId, $dados);
        $this->log('erro_gateway', ['cobranca_id'=>$cobrancaId,'provider'=>'asaas','status'=>$status,'http_code'=>$diagnostico['http_code'],'endpoint'=>$diagnostico['endpoint'],'method'=>$diagnostico['method'],'erro'=>$diagnostico['erro'],'external_reference'=>$referencia]);
    }

    private function persistirVencimentoEfetivo(int $cobrancaId, string $data): void
    {
        if(!$this->cobrancas->definirVencimentoEfetivo($cobrancaId, $data)){
            throw new \RuntimeException('Não foi possível persistir o vencimento efetivo da cobrança.');
        }
    }

    private function vencimentoEfetivoReconciliado(array $pagamento, array $cobranca, string $fallback): string
    {
        foreach([$pagamento['dueDate'] ?? null, $cobranca['COB_DataVencimentoEfetivo'] ?? null, $fallback] as $data){
            $data = trim((string) $data);
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)){
                [$ano,$mes,$dia] = array_map('intval', explode('-', $data));
                if(checkdate($mes, $dia, $ano)){ return $data; }
            }
        }
        throw new \RuntimeException('O gateway não informou um vencimento efetivo válido.');
    }

    private function sanitizarDadosGateway(array $dados, int $nivel = 0): array
    {
        if($nivel >= 4){ return []; }
        $seguro = [];
        foreach(array_slice($dados, 0, 50, true) as $chave=>$valor){
            if(is_string($chave) && preg_match('/token|authorization|secret|password|senha|credential|api.?key/i', $chave)){ continue; }
            $chaveNumerica = is_int($chave) || ctype_digit((string) $chave);
            $permitida = $chaveNumerica || in_array((string) $chave, ['errors','error','code','description','message','status','id','externalReference','dueDate','value','billingType'], true);
            if(!$permitida){ continue; }
            if(is_array($valor)){
                $seguro[$chave] = $this->sanitizarDadosGateway($valor, $nivel + 1);
            }elseif(is_scalar($valor) || $valor === null){
                $seguro[$chave] = is_string($valor) ? $this->sanitizarTextoGateway($valor) : $valor;
            }
        }
        return $seguro;
    }

    private function sanitizarTextoGateway($texto): string
    {
        $texto = preg_replace('/[\r\n\t]+/', ' ', trim((string) $texto));
        $texto = preg_replace('/(token|authorization|bearer|secret|password|senha|credential|api.?key)\s*[:=]?\s*\S+/i', '$1=[removido]', $texto);
        return mb_substr($texto, 0, 500, 'UTF-8');
    }

    private function log(string $evento, array $dados): void
    {
        $dir = function_exists('diretorioLogsProjeto') ? diretorioLogsProjeto() : dirname(__DIR__, 2) . '/storage/logs';
        if(!is_dir($dir)){ mkdir($dir, 0770, true); }
        unset($dados['token'], $dados['access_token'], $dados['password']);
        error_log(json_encode(['data'=>date('Y-m-d H:i:s'),'evento'=>$evento,'dados'=>$dados], JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $dir . '/financeiro-workflow.log');
    }
}
