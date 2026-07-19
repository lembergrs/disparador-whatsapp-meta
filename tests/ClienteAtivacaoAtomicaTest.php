<?php

require_once __DIR__ . '/../app/Models/Cliente.php';

use Models\Cliente;

class AtivacaoStatementFake
{
    private $db;
    public function __construct($db){$this->db=$db;}
    public function execute(){
        $this->db->execucoes++;
        if($this->db->execucoes===2){throw new RuntimeException('falha simulada em usuarios');}
        return true;
    }
}

class AtivacaoDatabaseFake
{
    public $transacao=false; public $rollback=false; public $commit=false; public $execucoes=0;
    public function inTransaction(){return $this->transacao;}
    public function beginTransaction(){$this->transacao=true;}
    public function commit(){$this->commit=true;$this->transacao=false;}
    public function rollBack(){$this->rollback=true;$this->transacao=false;}
    public function prepare(){return new AtivacaoStatementFake($this);}
}

$db=new AtivacaoDatabaseFake();
try{(new Cliente($db))->atualizarAtivacaoComUsuarios(1,'S','ativo');}catch(RuntimeException $e){}
if(!$db->rollback||$db->commit){throw new RuntimeException('falha parcial deve desfazer cliente e usuários');}

echo "ClienteAtivacaoAtomicaTest OK\n";
