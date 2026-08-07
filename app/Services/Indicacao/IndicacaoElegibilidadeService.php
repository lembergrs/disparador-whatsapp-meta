<?php

namespace Services\Indicacao;

use Core\Database;
use Models\Indicacao;
use Models\IndicacaoAuditoria;
use Models\IndicacaoCampanha;
use Models\IndicacaoCredito;
use Models\TarefaAgendada;
use PDO;
use PDOException;
use Services\IndicacaoAuditoriaService;
use Services\IndicacaoCreditoService;
use Services\IndicacaoService;
use Services\TaskSchedulerService;
use Services\Tasks\TaskPermanentFailureException;
use Services\Tasks\TaskRegistry;
use Services\Tasks\TaskRetryException;

class IndicacaoElegibilidadeService
{
    const TIPO_TAREFA = 'indicacao_confirmacao';
    const CHAVE_PREFIXO = 'indicacao_confirmacao_7d:';
    const JANELA_DIAS = 7;
    const TOLERANCIA_FUTURO_SEGUNDOS = 300;

    private $db;
    private $indicacoes;
    private $creditos;
    private $indicacaoService;
    private $creditoService;
    private $scheduler;
    private $relogio;

    public function __construct(PDO $db, Indicacao $indicacoes, IndicacaoCredito $creditos, IndicacaoService $indicacaoService, IndicacaoCreditoService $creditoService, TaskSchedulerService $scheduler, callable $relogio = null)
    {
        $this->db = $db;
        $this->indicacoes = $indicacoes;
        $this->creditos = $creditos;
        $this->indicacaoService = $indicacaoService;
        $this->creditoService = $creditoService;
        $this->scheduler = $scheduler;
        $this->relogio = $relogio;
    }

    public static function padrao(): self
    {
        $db = Database::getInstance();
        $indicacoes = new Indicacao($db);
        $creditos = new IndicacaoCredito($db);
        $auditoria = new IndicacaoAuditoriaService(new IndicacaoAuditoria($db));
        $indicacaoService = new IndicacaoService($db, $indicacoes, new IndicacaoCampanha($db), $auditoria);
        $creditoService = new IndicacaoCreditoService($db, $creditos, $indicacoes, $auditoria);
        $scheduler = new TaskSchedulerService(new TarefaAgendada($db), new TaskRegistry());
        return new self($db, $indicacoes, $creditos, $indicacaoService, $creditoService, $scheduler);
    }

    public function confirmarPrimeiroPagamento($clienteIndicadoId, \DateTimeInterface $pagoEm): array
    {
        $clienteIndicadoId = (int)$clienteIndicadoId;
        if($clienteIndicadoId <= 0) throw new \InvalidArgumentException('Cliente indicado inválido.');
        $pagamento = $this->normalizarData($pagoEm);
        if($pagamento > $this->agora()->modify('+' . self::TOLERANCIA_FUTURO_SEGUNDOS . ' seconds')){
            throw new \InvalidArgumentException('Data de pagamento futura inválida.');
        }

        return $this->transacao(function() use ($clienteIndicadoId, $pagamento){
            $indicacao = $this->indicacoes->buscarPorIndicado($clienteIndicadoId, true);
            if(!$indicacao) throw new \DomainException('Indicação não encontrada para o cliente informado.');

            if($indicacao['IND_Status'] === 'aprovada'){
                return ['id'=>(int)$indicacao['IND_ID'], 'idempotente'=>true, 'tarefa_id'=>null];
            }
            if($indicacao['IND_Status'] === 'em_confirmacao'){
                if(empty($indicacao['IND_ConfirmacaoAte'])) throw new \DomainException('Indicação em confirmação sem data limite.');
                $existente = $this->agendarConfirmacao(
                    (int)$indicacao['IND_ID'],
                    new \DateTimeImmutable($indicacao['IND_ConfirmacaoAte'], new \DateTimeZone(date_default_timezone_get()))
                );
                return ['id'=>(int)$indicacao['IND_ID'], 'idempotente'=>true, 'tarefa_id'=>(int)$existente['id']];
            }
            if($indicacao['IND_Status'] !== 'aguardando_pagamento'){
                throw new \DomainException('Indicação não está aguardando o primeiro pagamento.');
            }

            $confirmacaoAte = $pagamento->modify('+' . self::JANELA_DIAS . ' days');
            $this->indicacaoService->confirmarPagamento($indicacao['IND_ID'], $pagamento, $confirmacaoAte);
            $tarefa = $this->agendarConfirmacao((int)$indicacao['IND_ID'], $confirmacaoAte);
            $this->indicacaoService->transicionar($indicacao['IND_ID'], 'em_confirmacao');

            return [
                'id'=>(int)$indicacao['IND_ID'],
                'idempotente'=>!$tarefa['criada'],
                'tarefa_id'=>(int)$tarefa['id'],
                'confirmacao_ate'=>$confirmacaoAte->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function processarConfirmacao($indicacaoId): array
    {
        try{
            return $this->transacao(function() use ($indicacaoId){
                $indicacao = $this->indicacoes->buscar((int)$indicacaoId, true);
                if(!$indicacao) throw new TaskPermanentFailureException('Indicação não encontrada.');

                if(in_array($indicacao['IND_Status'], ['cancelada','fraude','inelegivel'], true)){
                    return ['resultado'=>'sem_acao'];
                }

                $credito = $this->creditos->buscarPorIndicacao($indicacao['IND_ID'], true);
                if($indicacao['IND_Status'] === 'aprovada'){
                    if(!$credito) throw new TaskPermanentFailureException('Inconsistência entre indicação aprovada e crédito.');
                    if($credito['ICR_Status'] !== 'liberado') throw new TaskPermanentFailureException('Inconsistência no estado do crédito aprovado.');
                    return ['resultado'=>'ja_processada', 'credito_id'=>(int)$credito['ICR_ID']];
                }
                if($indicacao['IND_Status'] !== 'em_confirmacao'){
                    throw new TaskPermanentFailureException('Estado da indicação incompatível com confirmação.');
                }
                if(empty($indicacao['IND_PagamentoConfirmadoEm']) || empty($indicacao['IND_ConfirmacaoAte'])){
                    throw new TaskPermanentFailureException('Indicação sem marcos de confirmação.');
                }
                if($credito){
                    throw new TaskPermanentFailureException('Crédito existente para indicação ainda não aprovada.');
                }

                $confirmacaoAte = new \DateTimeImmutable($indicacao['IND_ConfirmacaoAte'], new \DateTimeZone(date_default_timezone_get()));
                if($this->agora() < $confirmacaoAte){
                    throw new TaskRetryException('Janela de confirmação ainda não concluída.');
                }

                $this->indicacaoService->transicionar($indicacao['IND_ID'], 'aprovada');
                $creditoId = $this->creditoService->criar($indicacao['IND_ID']);
                $this->creditoService->transicionar($creditoId, 'em_confirmacao');
                $this->creditoService->transicionar($creditoId, 'liberado');

                return ['resultado'=>'liberada', 'credito_id'=>$creditoId];
            });
        }catch(TaskRetryException $e){
            throw $e;
        }catch(TaskPermanentFailureException $e){
            throw $e;
        }catch(PDOException $e){
            throw new TaskRetryException('Falha temporária ao persistir confirmação.', 0, $e);
        }catch(\Throwable $e){
            throw new TaskRetryException('Falha temporária ao processar confirmação.', 0, $e);
        }
    }

    private function normalizarData(\DateTimeInterface $data): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($data)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    private function agendarConfirmacao($indicacaoId, \DateTimeInterface $confirmacaoAte): array
    {
        return $this->scheduler->agendar(
            self::TIPO_TAREFA,
            ['indicacao_id'=>(int)$indicacaoId],
            $confirmacaoAte,
            self::CHAVE_PREFIXO . (int)$indicacaoId
        );
    }

    private function agora(): \DateTimeImmutable
    {
        $agora = $this->relogio ? call_user_func($this->relogio) : new \DateTimeImmutable('now');
        if(!$agora instanceof \DateTimeInterface) throw new \LogicException('Relógio de domínio inválido.');
        return $this->normalizarData($agora);
    }

    private function transacao(callable $callback)
    {
        $propria = !$this->db->inTransaction();
        if($propria) $this->db->beginTransaction();
        try{
            $resultado = $callback();
            if($propria) $this->db->commit();
            return $resultado;
        }catch(\Throwable $e){
            if($propria && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
