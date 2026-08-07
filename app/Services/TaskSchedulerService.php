<?php

namespace Services;

use Models\TarefaAgendada;
use Services\Tasks\TaskRegistry;

class TaskSchedulerService
{
    private const PAYLOAD_MAX_BYTES = 16384;
    private $tarefas;
    private $registry;

    public function __construct(TarefaAgendada $tarefas = null, TaskRegistry $registry = null)
    {
        $this->tarefas = $tarefas ?: new TarefaAgendada();
        $this->registry = $registry ?: new TaskRegistry();
    }

    public function agendar($tipo, array $payload, \DateTimeInterface $executarEm, $chaveIdempotencia = null, $prioridade = 100, $maxTentativas = 3): array
    {
        $this->validarTipo($tipo);
        $json = $this->validarPayload($payload);
        $chave = $this->validarChave($chaveIdempotencia);
        $prioridade = (int)$prioridade;
        $maxTentativas = (int)$maxTentativas;
        if($prioridade < 1 || $prioridade > 1000) throw new \InvalidArgumentException('Prioridade deve estar entre 1 e 1000.');
        if($maxTentativas < 1 || $maxTentativas > 20) throw new \InvalidArgumentException('Máximo de tentativas deve estar entre 1 e 20.');
        $data = \DateTimeImmutable::createFromInterface($executarEm)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $this->tarefas->inserir([
            'tipo'=>$tipo, 'payload'=>$json, 'executar_em'=>$data->format('Y-m-d H:i:s'),
            'chave_idempotencia'=>$chave, 'prioridade'=>$prioridade, 'max_tentativas'=>$maxTentativas,
        ]);
    }

    public function agendarAgora($tipo, array $payload, $chaveIdempotencia = null, $prioridade = 100, $maxTentativas = 3): array
    {
        return $this->agendar($tipo, $payload, new \DateTimeImmutable('now'), $chaveIdempotencia, $prioridade, $maxTentativas);
    }

    public function cancelar($id): bool
    {
        return $this->tarefas->cancelar((int)$id);
    }

    public function reagendar($id, \DateTimeInterface $executarEm): bool
    {
        $data = \DateTimeImmutable::createFromInterface($executarEm)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $this->tarefas->reagendar((int)$id, $data->format('Y-m-d H:i:s'));
    }

    private function validarTipo($tipo): void
    {
        if(!is_string($tipo) || !preg_match('/^[a-z][a-z0-9_]{2,79}$/', $tipo) || !$this->registry->possui($tipo)){
            throw new \InvalidArgumentException('Tipo de tarefa não permitido.');
        }
    }

    private function validarChave($chave)
    {
        if($chave === null || $chave === '') return null;
        if(!is_string($chave) || strlen($chave) > 190 || !preg_match('/^[A-Za-z0-9:_\-.]+$/', $chave)){
            throw new \InvalidArgumentException('Chave de idempotência inválida.');
        }
        return $chave;
    }

    private function validarPayload(array $payload): string
    {
        $this->validarValor($payload, 0);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if(strlen($json) > self::PAYLOAD_MAX_BYTES) throw new \InvalidArgumentException('Payload excede 16 KB.');
        return $json;
    }

    private function validarValor($valor, $nivel): void
    {
        if($nivel > 6) throw new \InvalidArgumentException('Payload excede a profundidade permitida.');
        if(is_resource($valor) || is_object($valor)) throw new \InvalidArgumentException('Payload contém valor não permitido.');
        if(!is_array($valor)) return;
        foreach($valor as $chave=>$item){
            if(is_string($chave) && preg_match('/token|secret|senha|password|authorization|credential|payload|sql|class|callable|function/i', $chave)){
                throw new \InvalidArgumentException('Payload contém chave sensível ou executável.');
            }
            $this->validarValor($item, $nivel + 1);
        }
    }
}
