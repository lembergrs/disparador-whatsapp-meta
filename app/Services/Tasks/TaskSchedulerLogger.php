<?php

namespace Services\Tasks;

class TaskSchedulerLogger
{
    private $arquivo;

    public function __construct($arquivo)
    {
        $this->arquivo = (string)$arquivo;
        $diretorio = dirname($this->arquivo);
        if(!is_dir($diretorio) && !mkdir($diretorio, 0770, true) && !is_dir($diretorio)){
            throw new TaskSchedulerLoggingException('Não foi possível inicializar o diretório de logs.');
        }
        if(!is_writable($diretorio)){
            throw new TaskSchedulerLoggingException('O diretório de logs não permite escrita.');
        }
    }

    public function __invoke(array $evento): void
    {
        $permitidos = ['data','nivel','tarefa_id','tipo','status','tentativa','duracao_ms','erro_codigo'];
        $linha = array_intersect_key($evento, array_flip($permitidos));
        $linha['data'] = isset($linha['data']) ? (string)$linha['data'] : date('c');
        $linha['nivel'] = in_array($linha['nivel'] ?? null, ['INFO','WARNING','ERROR'], true) ? $linha['nivel'] : 'ERROR';
        $json = json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if($json === false || file_put_contents($this->arquivo, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false){
            throw new TaskSchedulerLoggingException('Não foi possível gravar o log do scheduler.');
        }
    }

    public function erroOperacional(\Throwable $erro): void
    {
        $this([
            'data'=>date('c'),
            'nivel'=>'ERROR',
            'status'=>'erro_operacional',
            'erro_codigo'=>$this->codigoOperacional($erro),
        ]);
    }

    private function codigoOperacional(\Throwable $erro): string
    {
        $classe = get_class($erro);
        if($erro instanceof \PDOException) return 'banco_indisponivel';
        if($erro instanceof \Error) return 'erro_execucao';
        if(strpos($classe, 'Filesystem') !== false) return 'falha_arquivo';
        return 'falha_inicializacao_ou_processamento';
    }
}
