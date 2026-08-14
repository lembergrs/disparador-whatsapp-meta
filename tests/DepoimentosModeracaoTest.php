<?php
$root = dirname(__DIR__);
require $root . '/app/Models/DepoimentoCliente.php';
use Models\DepoimentoCliente;
$assert = function($ok,$message){ if(!$ok){ fwrite(STDERR,"FAIL: {$message}\n"); exit(1); } };
class DepFakePdo extends PDO {
    public array $rows=[]; private int $id=0;
    public function __construct(){}
    public function prepare($query,$options=[]): PDOStatement|false { return new DepFakeStatement($this,$query); }
    public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false { $s=new DepFakeStatement($this,$query); $s->execute(); return $s; }
    public function lastInsertId(?string $name=null): string|false { return (string)$this->id; }
    public function nextId(): int { return ++$this->id; }
}
class DepFakeStatement extends PDOStatement {
    private DepFakePdo $db; private string $sql; private array $result=[]; private int $affected=0;
    public function __construct(DepFakePdo $db,string $sql){ $this->db=$db; $this->sql=$sql; }
    public function execute(?array $params=null): bool { $p=$params??[]; $this->affected=0;
        if(str_contains($this->sql,'INSERT INTO')){ $id=$this->db->nextId(); $this->db->rows[$id]=['DEP_ID'=>$id,'CLI_ID'=>$p[0],'DEP_NomeExibido'=>$p[1],'DEP_Empresa'=>$p[2],'DEP_Cargo'=>$p[3],'DEP_Depoimento'=>$p[4],'DEP_Autorizado'=>'S','DEP_Status'=>'pendente','DEP_Ativo'=>'S']; $this->affected=1; }
        elseif(str_contains($this->sql,"DEP_Status = ?")){ $id=(int)$p[3]; if(isset($this->db->rows[$id])&&$this->db->rows[$id]['DEP_Status']==='pendente'){ $this->db->rows[$id]['DEP_Status']=$p[0]; $this->db->rows[$id]['DEP_Ativo']=$p[0]==='aprovado'?'S':'N'; $this->affected=1; } }
        elseif(str_contains($this->sql,"SET DEP_Ativo = 'N'")){ $id=(int)$p[1]; if(isset($this->db->rows[$id])&&$this->db->rows[$id]['DEP_Status']==='aprovado'&&$this->db->rows[$id]['DEP_Ativo']==='S'){ $this->db->rows[$id]['DEP_Ativo']='N'; $this->affected=1; } }
        if(str_contains($this->sql,'SELECT DEP_NomeExibido')){ $this->result=array_values(array_filter($this->db->rows,fn($r)=>$r['DEP_Status']==='aprovado'&&$r['DEP_Ativo']==='S'&&$r['DEP_Autorizado']==='S')); $this->result=array_map(fn($r)=>array_intersect_key($r,array_flip(['DEP_NomeExibido','DEP_Empresa','DEP_Cargo','DEP_Depoimento'])),$this->result); }
        return true;
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,...$args): array { return $this->result; }
    public function rowCount(): int { return $this->affected; }
}
$pdo = new DepFakePdo();
$model = new DepoimentoCliente($pdo);
$pendente = $model->criarPendente(8,['nome'=>'Ana','empresa'=>'Empresa A','cargo'=>'Gestora','depoimento'=>'Experiência real']);
$assert($model->listarPublicados() === [], 'pendente não aparece');
$assert($model->decidir($pendente,'rejeitado',1), 'admin rejeita pendente');
$assert($model->listarPublicados() === [], 'rejeitado não aparece');
$aprovado = $model->criarPendente(8,['nome'=>'Bia','empresa'=>'Empresa B','cargo'=>'','depoimento'=>'Atendimento organizado']);
$assert($model->decidir($aprovado,'aprovado',2), 'admin aprova pendente');
$publicados = $model->listarPublicados();
$assert(count($publicados)===1 && $publicados[0]['DEP_NomeExibido']==='Bia', 'aprovado e ativo aparece sem dados privados');
$assert(!$model->decidir($aprovado,'rejeitado',2), 'decisão não pode ser repetida');
$assert($model->desativar($aprovado,2) && $model->listarPublicados()===[], 'aprovado pode ser desativado');
$clienteCtl = file_get_contents($root.'/app/Controllers/DepoimentoController.php');
$adminCtl = file_get_contents($root.'/app/Controllers/DepoimentoAdminController.php');
$clienteView = file_get_contents($root.'/app/Views/depoimentos/index.php');
$adminView = file_get_contents($root.'/app/Views/depoimentos_admin/index.php');
$assert(strpos($clienteCtl,'Auth::cliente();')!==false, 'envio exige cliente autenticado');
$assert(strpos($clienteCtl,'$this->validarCsrfPost();')!==false && strpos($clienteView,'Csrf::input()')!==false, 'envio exige CSRF');
$assert(strpos($clienteCtl,'strip_tags')!==false && strpos($clienteCtl,"1000")!==false, 'conteúdo é sanitizado e limitado');
$assert(strpos($clienteCtl,"criarPendente")!==false && strpos($clienteCtl,"decidir")===false, 'cliente não possui aprovação');
$assert(substr_count($adminCtl,'Auth::admin();')>=3 && substr_count($adminCtl,'$this->validarCsrfPost();')>=2, 'ações administrativas exigem admin e CSRF');
$assert(strpos($adminView,'Csrf::input()')!==false, 'formulários administrativos incluem CSRF');
echo "Testimonials moderation tests passed\n";
