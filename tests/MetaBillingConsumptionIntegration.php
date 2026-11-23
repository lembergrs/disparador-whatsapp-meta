<?php
require_once __DIR__.'/../app/Services/MetaBillingConsumptionReversalService.php';
use Services\MetaBillingConsumptionReversalService;
$db=new PDO('mysql:host=127.0.0.1;dbname=consumo_test','root','test',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE consumo_mensal (CMS_ID INT PRIMARY KEY, CLI_ID INT, CMS_AnoMes CHAR(6), CMS_Mensagens INT, CMS_MensagensAvaliacao INT, CMS_InicioPlanoPago DATETIME NULL, CMS_LimiteMensagens INT, CMS_AtualizadoEm DATETIME, UNIQUE(CLI_ID,CMS_AnoMes)) ENGINE=InnoDB");
$db->exec("CREATE TABLE excedentes_mensais (CLI_ID INT, EXC_AnoMes CHAR(6), EXC_Mensagens INT, EXC_ValorUnitario DECIMAL(10,2), EXC_ValorTotal DECIMAL(10,2)) ENGINE=InnoDB");
$db->exec("CREATE TABLE disparo_manual_itens (DMI_ID INT PRIMARY KEY, CLI_ID INT, DMI_MessageId VARCHAR(255), DMI_DataEnvio DATETIME, DMI_DataCadastro DATETIME, DMI_Status VARCHAR(40)) ENGINE=InnoDB");
$db->exec("CREATE TABLE campanhas (CAM_ID INT PRIMARY KEY, CLI_ID INT) ENGINE=InnoDB");
$db->exec("CREATE TABLE fila_envio (FIL_ID INT PRIMARY KEY, CAM_ID INT, FIL_MessageId VARCHAR(255), FIL_DataEnvio DATETIME, FIL_DataCadastro DATETIME, FIL_Status VARCHAR(40)) ENGINE=InnoDB");
$db->exec("CREATE TABLE cobrancas (COB_ID INT PRIMARY KEY, CLI_ID INT, COB_Status VARCHAR(30), COB_Tipo VARCHAR(30), COB_AdicionaisCentavos INT, COB_DataVencimento DATE) ENGINE=InnoDB");
foreach (['20260922_create_consumo_estornos_meta.sql','20261123_create_consumo_envios_meta.sql'] as $file) $db->exec(file_get_contents(__DIR__.'/../database/migrations/'.$file));
$m=date('Ym');
$db->exec("INSERT INTO consumo_mensal VALUES (1,1,'$m',12,0,NOW(),10,NOW())");
$db->exec("INSERT INTO excedentes_mensais VALUES (1,'$m',2,0.10,0.20)");
$s=new MetaBillingConsumptionReversalService($db);
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
function seed($db,$id,$m){$db->exec("INSERT INTO disparo_manual_itens VALUES ($id,1,'w$id',NOW(),NOW(),'aguardando_confirmacao')");$db->exec("INSERT INTO consumo_envios_meta VALUES ('w$id',1,'$m',NOW())");}
check(!$s->processar('w1',['codigo'=>'131049']),'falha antecipada');
seed($db,1,$m);
check($s->reconciliar('w1'),'reconciliacao');
check(!$s->reconciliar('w1'),'duplicidade');
check((int)$db->query('SELECT CMS_Mensagens FROM consumo_mensal')->fetchColumn()===11,'consumo');
check((int)$db->query('SELECT EXC_Mensagens FROM excedentes_mensais')->fetchColumn()===1,'excedente');
seed($db,2,$m);
$db->exec("INSERT INTO cobrancas VALUES (1,1,'pago','excedente',10,CURDATE())");
check(!$s->processar('w2',['codigo'=>'131049']),'cobranca protegida');
check((int)$db->query('SELECT CMS_Mensagens FROM consumo_mensal')->fetchColumn()===11,'saldo preservado');
echo "Integration OK\n";
