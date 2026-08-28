<?php

namespace Services;

use Models\Assinatura;
use Models\Cliente;
use Models\Cobranca;
use Models\Notificacao;
use Models\Plano;
use Services\Tasks\TaskRetryException;

class FinanceiroNotificacaoService
{
    private $cobrancas;
    private $assinaturas;
    private $clientes;
    private $planos;
    private $notificacoes;
    private $entregas;
    private $scheduler;
    private $policy;
    private $agora;

    public function __construct($cobrancas = null, $assinaturas = null, $clientes = null, $planos = null, $notificacoes = null, $entregas = null, $scheduler = null, $policy = null, callable $agora = null)
    {
        $this->cobrancas = $cobrancas ?: new Cobranca();
        $this->assinaturas = $assinaturas ?: new Assinatura();
        $this->clientes = $clientes ?: new Cliente();
        $this->planos = $planos ?: new Plano();
        $this->notificacoes = $notificacoes ?: new Notificacao();
        $this->entregas = $entregas ?: new NotificacaoService();
        $this->scheduler = $scheduler ?: new TaskSchedulerService();
        $this->policy = $policy ?: new FinanceiroAccessPolicyService($this->assinaturas, $this->cobrancas, $agora);
        $this->agora = $agora ?: function(){ return new \DateTimeImmutable('today'); };
    }

    public function planejar(): array
    {
        $resultado = ['analisadas'=>0, 'reservadas'=>0];
        foreach($this->cobrancas->listarAbertasParaComunicacao() as $cobranca){
            $resultado['analisadas']++;
            $evento = $this->eventoElegivel($cobranca);
            if($evento){ $resultado['reservadas'] += $this->reservarEvento($cobranca, $evento); }
        }
        return $resultado;
    }

    public function situacaoAntesPagamento(int $clienteId): string
    {
        $situacao = (string) ($this->policy->avaliar($clienteId)['situacao'] ?? 'regular');
        return in_array($situacao, ['regular','tolerancia','suspenso'], true) ? $situacao : 'regular';
    }

    public function agendarPagamentoConfirmado(int $cobrancaId, string $situacaoAnterior = 'regular'): int
    {
        $cobranca = $this->cobrancas->buscar($cobrancaId);
        if(!$cobranca || ($cobranca['COB_Status'] ?? '') !== 'pago'){ return 0; }
        return $this->reservarEvento($cobranca, EventoNotificacao::PAGAMENTO_CONFIRMADO, $situacaoAnterior);
    }

    public function enviar(int $notificacaoId): void
    {
        $registro = $this->notificacoes->buscar($notificacaoId);
        if(!$registro || in_array($registro['NOT_Status'] ?? '', ['enviada','entregue','lida','ignorada','erro_definitivo'], true)){ return; }
        $cobranca = $this->cobrancas->buscar((int) ($registro['COB_ID'] ?? 0));
        if(!$cobranca || !$this->registroContinuaElegivel($registro, $cobranca)){
            $this->notificacoes->marcarIgnorada($notificacaoId, 'contexto_financeiro_nao_elegivel');
            return;
        }
        $lease = defined('TASK_SCHEDULER_LEASE_MINUTES') ? TASK_SCHEDULER_LEASE_MINUTES : 15;
        if(!$this->notificacoes->marcarProcessando($notificacaoId, 5, $lease)){
            $atual = $this->notificacoes->buscar($notificacaoId);
            if(in_array($atual['NOT_Status'] ?? '', ['enviada','entregue','lida','ignorada','erro_definitivo'], true)){ return; }
            throw new TaskRetryException('A comunicação financeira já está reservada por outro processo.');
        }
        $cliente = $this->clientes->buscar((int) $cobranca['CLI_ID']);
        if(!$cliente){ $this->notificacoes->marcarIgnorada($notificacaoId, 'cliente_nao_encontrado'); return; }
        $dados = $this->dadosEvento($cobranca, (string) $registro['NOT_Tipo'], $this->dadosPersistidos($registro));
        $resultado = $this->entregas->entregarCanalReservado($registro['NOT_Tipo'], $registro['NOT_Canal'], $cliente, $dados);
        $this->notificacoes->marcarResultadoFinanceiro($notificacaoId, $resultado);
        if(empty($resultado['sucesso']) && ($resultado['status'] ?? 'erro_temporario') === 'erro_temporario'){
            throw new TaskRetryException('Falha transitória no envio da comunicação financeira.');
        }
    }

    private function reservarEvento(array $cobranca, string $evento, string $situacaoAnterior = 'regular'): int
    {
        $cliente = $this->clientes->buscar((int) $cobranca['CLI_ID']);
        if(!$cliente){ return 0; }
        $dados = $this->dadosEvento($cobranca, $evento, ['situacao_anterior'=>$situacaoAnterior]);
        $reservadas = 0;
        foreach($this->entregas->canaisAtivos($evento) as $canal){
            $chave = 'financeiro:' . (int) $cobranca['COB_ID'] . ':' . $evento . ':' . $canal;
            $registro = $this->notificacoes->reservarIdempotente([
                'cliente_id'=>(int)$cobranca['CLI_ID'], 'cobranca_id'=>(int)$cobranca['COB_ID'], 'tipo'=>$evento,
                'canal'=>$canal, 'destino'=>$canal === CanalNotificacao::EMAIL ? ($cliente['CLI_Email'] ?? '') : ($cliente['CLI_Telefone'] ?? ''),
                'dados'=>$dados, 'data_referencia'=>$this->vencimento($cobranca), 'chave'=>$chave,
            ]);
            if(!$registro){ continue; }
            $this->scheduler->agendarAgora('financeiro_enviar_comunicacao', ['notificacao_id'=>(int)$registro['NOT_ID']], 'financeiro:entrega:' . (int)$registro['NOT_ID'], 30, 5);
            $reservadas++;
        }
        return $reservadas;
    }

    private function eventoElegivel(array $cobranca): ?string
    {
        if(!$this->obrigacaoAtual($cobranca)){ return null; }
        $hoje = $this->hoje();
        $vencimento = $this->data($this->vencimento($cobranca));
        if(!$vencimento){ return null; }
        $diferenca = (int) $hoje->diff($vencimento)->format('%r%a');
        if($diferenca === 0){ return null; }
        $competencia = $this->data((string)($cobranca['COB_DataVencimento'] ?? ''));
        $recuperada = $competencia && $competencia < $hoje && $competencia != $vencimento;
        if($recuperada && $diferenca > 0 && $this->possuiMeioPagamento($cobranca)){ return EventoNotificacao::COBRANCA_DISPONIVEL; }
        if($diferenca === 3){ return EventoNotificacao::LEMBRETE_VENCIMENTO_D3; }
        if($diferenca > 0 && $diferenca <= 7 && $this->possuiMeioPagamento($cobranca)){ return EventoNotificacao::COBRANCA_DISPONIVEL; }
        if($diferenca >= 0){ return null; }
        $dias = abs($diferenca);
        $mapa = [1=>EventoNotificacao::COBRANCA_VENCIDA_D1, 3=>EventoNotificacao::LEMBRETE_VENCIDA_D3, 5=>EventoNotificacao::AVISO_SUSPENSAO_D5, 7=>EventoNotificacao::SUSPENSAO_INADIMPLENCIA_D7];
        if(!isset($mapa[$dias])){ return null; }
        $politica = $this->policy->avaliar((int)$cobranca['CLI_ID']);
        $situacaoEsperada = $dias === 7 ? 'suspenso' : 'tolerancia';
        return (int)($politica['cobranca_id'] ?? 0) === (int)$cobranca['COB_ID'] && ($politica['situacao'] ?? '') === $situacaoEsperada ? $mapa[$dias] : null;
    }

    private function registroContinuaElegivel(array $registro, array $cobranca): bool
    {
        if(($registro['NOT_Tipo'] ?? '') === EventoNotificacao::PAGAMENTO_CONFIRMADO){
            $ativa = $this->assinaturas->buscarAtivaPorCliente((int)$cobranca['CLI_ID']);
            return ($cobranca['COB_Status'] ?? '') === 'pago' && $ativa && (int)$ativa['ASS_ID'] === (int)($cobranca['ASS_ID'] ?? 0);
        }
        return in_array($cobranca['COB_Status'] ?? '', ['pendente','vencido'], true)
            && ($registro['NOT_Tipo'] ?? '') === $this->eventoElegivel($cobranca);
    }

    private function obrigacaoAtual(array $cobranca): bool
    {
        $assinatura = $this->assinaturas->buscarParaRegularizacaoFinanceira((int)$cobranca['CLI_ID']);
        if(!$assinatura || (int)$assinatura['ASS_ID'] !== (int)($cobranca['ASS_ID'] ?? 0)){ return false; }
        $obrigacao = $this->cobrancas->buscarObrigacaoAbertaPorAssinatura((int)$cobranca['CLI_ID'], (int)$assinatura['ASS_ID']);
        return $obrigacao && (int)$obrigacao['COB_ID'] === (int)$cobranca['COB_ID'];
    }

    private function dadosEvento(array $cobranca, string $evento, array $persistidos = []): array
    {
        $plano = $this->planos->buscar((int)($cobranca['PLA_ID'] ?? 0));
        $vencimento = $this->vencimento($cobranca);
        $dias = max(0, (int)$this->data($vencimento)->diff($this->hoje())->format('%r%a'));
        $contextos = ['regular'=>'Pagamento confirmado com sucesso.','tolerancia'=>'Pagamento confirmado e situação financeira regularizada.','suspenso'=>'Pagamento confirmado; a situação foi regularizada e o acesso operacional será restabelecido automaticamente.'];
        return [
            'plano'=>$plano['PLA_Nome'] ?? ($cobranca['PLA_Nome'] ?? ''), 'valor'=>number_format((float)($cobranca['COB_Valor'] ?? 0), 2, ',', '.'),
            'vencimento'=>date('d/m/Y', strtotime($vencimento)), 'dias_atraso'=>$dias,
            'dias'=>max(0, (int)$this->hoje()->diff($this->data($vencimento))->format('%r%a')),
            'link'=>trim((string)($cobranca['COB_LinkPagamento'] ?? '')) ?: rtrim(BASE_URL, '/') . '/index.php?url=financeiro',
            'contexto_pagamento'=>$contextos[$persistidos['situacao_anterior'] ?? 'regular'] ?? $contextos['regular'],
            'situacao_anterior'=>$persistidos['situacao_anterior'] ?? 'regular', 'evento'=>$evento,
        ];
    }

    private function dadosPersistidos(array $registro): array
    {
        $dados = json_decode((string)($registro['NOT_Dados'] ?? ''), true);
        return is_array($dados) ? $dados : [];
    }

    private function possuiMeioPagamento(array $cobranca): bool
    {
        $link = trim((string)($cobranca['COB_LinkPagamento'] ?? ''));
        return ($link !== '' && filter_var($link, FILTER_VALIDATE_URL)) || trim((string)($cobranca['COB_PixCopiaCola'] ?? '')) !== '' || trim((string)($cobranca['COB_LinhaDigitavel'] ?? '')) !== '';
    }

    private function vencimento(array $cobranca): string { return (string)($cobranca['COB_VencimentoFinanceiro'] ?? $cobranca['COB_DataVencimentoEfetivo'] ?? $cobranca['COB_DataVencimento'] ?? ''); }
    private function data(string $data): ?\DateTimeImmutable { $valor=\DateTimeImmutable::createFromFormat('!Y-m-d',$data); return $valor ?: null; }
    private function hoje(): \DateTimeImmutable { $agora=call_user_func($this->agora); if(!$agora instanceof \DateTimeInterface){throw new \LogicException('Relógio financeiro inválido.');} return \DateTimeImmutable::createFromInterface($agora)->setTime(0,0); }
}
