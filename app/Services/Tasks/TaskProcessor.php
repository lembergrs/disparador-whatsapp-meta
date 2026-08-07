<?php

namespace Services\Tasks;

use Models\TarefaAgendada;

class TaskProcessor
{
    private $tarefas;
    private $dispatcher;
    private $retry;
    private $leaseMinutos;
    private $logger;

    public function __construct(TarefaAgendada $tarefas, TaskDispatcher $dispatcher, TaskRetryPolicy $retry = null, $leaseMinutos = 15, callable $logger = null)
    {
        $this->tarefas = $tarefas;
        $this->dispatcher = $dispatcher;
        $this->retry = $retry ?: new TaskRetryPolicy();
        $this->leaseMinutos = max(1, (int)$leaseMinutos);
        $this->logger = $logger;
    }

    public function processarLote($limite, $workerId = null): array
    {
        $limite = max(1, min(500, (int)$limite));
        $workerId = $workerId ?: $this->workerId();
        $resumo = ['processadas'=>0, 'concluidas'=>0, 'retry'=>0, 'falhas'=>0];
        for($i=0; $i<$limite; $i++){
            $tarefa = $this->tarefas->reservarProxima($workerId, $this->leaseMinutos);
            if(!$tarefa) break;
            $resumo['processadas']++;
            $inicio = microtime(true);
            if(!empty($tarefa['recuperada'])){
                $this->log($tarefa, 'lease_recuperada', $inicio, 'lease_expirado_recuperado');
            }
            try{
                $this->dispatcher->executar($tarefa);
                $this->tarefas->concluir($tarefa['TAG_ID'], $workerId, date('Y-m-d H:i:s'));
                $resumo['concluidas']++;
                $this->log($tarefa, 'concluida', $inicio);
            }catch(TaskSchedulerLoggingException $e){
                throw $e;
            }catch(TaskRetryException $e){
                if((int)$tarefa['TAG_Tentativas'] >= (int)$tarefa['TAG_MaxTentativas']){
                    $this->falhar($tarefa, $workerId, $e, $inicio, $resumo, 'max_tentativas');
                }else{
                    $proxima = $this->retry->proximaTentativa((int)$tarefa['TAG_Tentativas'])->format('Y-m-d H:i:s');
                    $this->tarefas->reagendarRetry($tarefa['TAG_ID'], $workerId, $proxima, $this->sanitizarErro($e->getMessage()));
                    $resumo['retry']++;
                    $this->log($tarefa, 'retry', $inicio, 'falha_temporaria');
                }
            }catch(TaskPermanentFailureException $e){
                $this->falhar($tarefa, $workerId, $e, $inicio, $resumo, 'falha_permanente');
            }catch(\Throwable $e){
                $this->falhar($tarefa, $workerId, $e, $inicio, $resumo, 'excecao_nao_classificada');
            }
        }
        return $resumo;
    }

    private function falhar(array $tarefa, $workerId, \Throwable $erro, $inicio, array &$resumo, $codigo): void
    {
        $this->tarefas->falhar($tarefa['TAG_ID'], $workerId, date('Y-m-d H:i:s'), $this->sanitizarErro($erro->getMessage()));
        $resumo['falhas']++;
        $this->log($tarefa, 'falha', $inicio, $codigo);
    }

    private function sanitizarErro($erro): string
    {
        $erro = preg_replace('/[\r\n\t]+/', ' ', trim((string)$erro));
        $erro = preg_replace('/(token|authorization|bearer|secret|password|senha|credential|payload)\s*[:=]?\s*\S+/i', '$1=[removido]', $erro);
        return mb_substr($erro, 0, 500, 'UTF-8');
    }

    private function workerId(): string
    {
        return substr((gethostname() ?: 'host') . ':' . getmypid() . ':' . bin2hex(random_bytes(4)), 0, 100);
    }

    private function log(array $tarefa, $status, $inicio, $codigo = null): void
    {
        if(!$this->logger) return;
        $nivel = $status === 'concluida' ? 'INFO' : ($status === 'falha' ? 'ERROR' : 'WARNING');
        call_user_func($this->logger, [
            'data'=>date('c'), 'nivel'=>$nivel, 'tarefa_id'=>(int)$tarefa['TAG_ID'], 'tipo'=>$tarefa['TAG_Tipo'],
            'status'=>$status, 'tentativa'=>(int)$tarefa['TAG_Tentativas'],
            'duracao_ms'=>(int)round((microtime(true)-$inicio)*1000), 'erro_codigo'=>$codigo,
        ]);
    }
}
