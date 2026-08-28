<?php

require_once __DIR__ . '/../app/Services/FinanceiroAccessPolicyService.php';
require_once __DIR__ . '/../app/Services/WorkerOperationalValidatorService.php';

use Services\FinanceiroAccessPolicyService;
use Services\WorkerOperationalValidatorService;

function accessAssert($condicao, $mensagem){ if(!$condicao){ throw new RuntimeException($mensagem); } }

class AccessAssinaturasFake
{
    public $ativa=['ASS_ID'=>10,'CLI_ID'=>1,'ASS_Status'=>'ativa'];
    public function buscarAtivaPorCliente($clienteId){ return $this->ativa && (int)$clienteId===1 ? $this->ativa : false; }
}

class AccessCobrancasFake
{
    public $rows=[];
    public function buscarObrigacaoVencidaPorAssinatura($clienteId,$assinaturaId,$hoje){
        $elegiveis=array_filter($this->rows,function($r)use($clienteId,$assinaturaId,$hoje){
            $v=$r['COB_DataVencimentoEfetivo']??$r['COB_DataVencimento'];
            return (int)$r['CLI_ID']===(int)$clienteId&&(int)($r['ASS_ID']??0)===(int)$assinaturaId&&in_array($r['COB_Status'],['pendente','vencido'],true)&&$v<$hoje;
        });
        usort($elegiveis,function($a,$b){return strcmp($a['COB_DataVencimentoEfetivo']??$a['COB_DataVencimento'],$b['COB_DataVencimentoEfetivo']??$b['COB_DataVencimento']);});
        if(!$elegiveis)return false;$r=$elegiveis[0];$r['COB_VencimentoFinanceiro']=$r['COB_DataVencimentoEfetivo']??$r['COB_DataVencimento'];return $r;
    }
}
class AccessLoggerFake {public $rows=[];public function __invoke($dados){$this->rows[]=$dados;}}

function accessPolicy($vencimento,$status='vencido',$assinaturaId=10,$efetivo=true){
    $assinaturas=new AccessAssinaturasFake();$cobrancas=new AccessCobrancasFake();
    if($vencimento!==null){$row=['COB_ID'=>20,'CLI_ID'=>1,'ASS_ID'=>$assinaturaId,'COB_Status'=>$status,'COB_DataVencimento'=>'2026-08-01'];if($efetivo)$row['COB_DataVencimentoEfetivo']=$vencimento;else $row['COB_DataVencimento']=$vencimento;$cobrancas->rows[]=$row;}
    $logs=new AccessLoggerFake();$policy=new FinanceiroAccessPolicyService($assinaturas,$cobrancas,function(){return new DateTimeImmutable('2026-09-08');},$logs,7);
    return [$policy,$cobrancas,$logs];
}

[$p]=accessPolicy('2026-09-08');accessAssert($p->avaliar(1)['situacao']==='regular','D0 não bloqueia');
[$p]=accessPolicy('2026-09-07');$r=$p->avaliar(1);accessAssert($r['situacao']==='tolerancia'&&$r['dias_atraso']===1&&$r['acesso_operacional'],'D+1 permanece em tolerância');
[$p]=accessPolicy('2026-09-02');accessAssert($p->avaliar(1)['situacao']==='tolerancia','D+6 permanece em tolerância');
[$p,$c,$logs]=accessPolicy('2026-09-01');$r=$p->avaliar(1);accessAssert($r['situacao']==='suspenso'&&$r['dias_atraso']===7&&!$r['acesso_operacional']&&$r['cobranca_id']===20,'D+7 suspende com cobrança responsável');accessAssert(count($logs->rows)===1&&$logs->rows[0]['regra']==='inadimplencia_d_7','suspensão gera log técnico');
[$p]=accessPolicy('2026-08-31');accessAssert($p->avaliar(1)['situacao']==='suspenso','D+8 continua suspenso');
[$p]=accessPolicy('2026-09-05','vencido',10,true);accessAssert($p->avaliar(1)['dias_atraso']===3,'vencimento efetivo prevalece sobre competência antiga');
[$p]=accessPolicy('2026-09-01','vencido',10,false);accessAssert($p->avaliar(1)['situacao']==='suspenso','vencimento contratual é fallback quando efetivo é nulo');
[$p]=accessPolicy('2026-08-01','vencido',null,true);accessAssert($p->avaliar(1)['situacao']==='regular','cobrança histórica sem assinatura não bloqueia');
[$p]=accessPolicy('2026-08-01','cancelado');accessAssert($p->avaliar(1)['situacao']==='regular','cobrança cancelada não bloqueia');
[$p]=accessPolicy('2026-08-01','pago');accessAssert($p->avaliar(1)['situacao']==='regular','cobrança paga não bloqueia');
[$p,$c]=accessPolicy('2026-09-01');accessAssert($p->avaliar(1)['situacao']==='suspenso','cliente começa suspenso');$c->rows[0]['COB_Status']='pago';accessAssert($p->avaliar(1)['situacao']==='regular','pagamento libera imediatamente pela política local');

class AccessClienteFake {public $row=['CLI_ID'=>1,'CLI_Ativo'=>'S','CLI_StatusCadastro'=>'ativo','CLI_StatusPagamento'=>'pago','PLA_LimiteMensagens'=>1000];public function buscarComPlano($id){return $this->row;}}
class AccessMetaFake {public function buscarPorCliente(){return ['MTA_Token'=>'x','MTA_PhoneNumberId'=>'1','MTA_UrlBase'=>'https://meta.test','MTA_Status'=>'conectado'];}}
class AccessConsumoFake {public function buscarMesAtual(){return ['CMS_Mensagens'=>0];}}
class AccessPolicyFake {public $resultado;public function avaliar(){return $this->resultado;}}
$policyFake=new AccessPolicyFake();$policyFake->resultado=['situacao'=>'tolerancia','vinculo_ativo'=>true,'acesso_operacional'=>true,'cobranca_id'=>20,'dias_atraso'=>6,'vencimento'=>'2026-09-02'];
$worker=new WorkerOperationalValidatorService(new AccessClienteFake(),new AccessMetaFake(),new AccessConsumoFake(),$policyFake);
accessAssert($worker->validarEnvio(1,1,'41999999999')['permitido'],'worker permite cobrança em tolerância');
$policyFake->resultado=['situacao'=>'suspenso','vinculo_ativo'=>true,'acesso_operacional'=>false,'cobranca_id'=>20,'dias_atraso'=>7,'vencimento'=>'2026-09-01','regra'=>'inadimplencia_d_7'];
$bloqueio=$worker->validarEnvio(1,1,'41999999999');accessAssert(!$bloqueio['permitido']&&$bloqueio['codigo']==='financeiro_inadimplente_d7'&&$bloqueio['financeiro']['cobranca_id']===20,'worker bloqueia pela mesma política D+7');

echo "FinanceiroAccessPolicyServiceTest OK\n";
