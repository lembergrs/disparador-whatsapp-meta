<?php

require_once __DIR__ . '/../app/Services/AsaasService.php';

use Services\AsaasService;

function adfAssert($condition, $message){ if(!$condition){ throw new RuntimeException($message); } }

class AsaasDescontoFixoSpy extends AsaasService
{
    public $requests = [];
    public function __construct(){}
    public function request($method, $endpoint, $payload = null)
    {
        $this->requests[] = compact('method', 'endpoint', 'payload');
        return ['sucesso'=>true, 'response'=>['id'=>'pay_teste']];
    }
}

function adfCobranca(array $dados = [])
{
    return array_merge([
        'COB_ID'=>10,
        'COB_DataVencimento'=>'2026-08-20',
        'COB_Valor'=>'85.00',
        'COB_ValorBaseCentavos'=>10000,
        'COB_DescontoInicialCentavos'=>0,
        'COB_DescontoIndicacaoCentavos'=>1500,
        'COB_AdicionaisCentavos'=>0
    ], $dados);
}

$cliente = ['CLI_ProviderCustomerId'=>'cus_teste'];
$asaas = new AsaasDescontoFixoSpy();
$asaas->criarCobranca($cliente, adfCobranca());
$payload = $asaas->requests[0]['payload'];
adfAssert($payload['billingType']==='UNDEFINED'&&$payload['value']===100.0, 'envia valor nominal com UNDEFINED');
adfAssert($payload['discount']===['value'=>15.0,'type'=>'FIXED','dueDateLimitDays'=>0], 'envia desconto de indicação fixo até o vencimento');

$asaas = new AsaasDescontoFixoSpy();
$asaas->criarCobranca($cliente, adfCobranca(['COB_Valor'=>'50.00','COB_DescontoInicialCentavos'=>5000,'COB_DescontoIndicacaoCentavos'=>0]));
$payload = $asaas->requests[0]['payload'];
adfAssert($payload['value']===100.0&&$payload['discount']['value']===50.0, 'primeira cobrança envia benefício inicial como desconto fixo');

$asaas = new AsaasDescontoFixoSpy();
$asaas->criarCobranca($cliente, ['COB_ID'=>11,'COB_DataVencimento'=>'2026-08-20','COB_Valor'=>'100.00']);
$payload = $asaas->requests[0]['payload'];
adfAssert($payload['value']===100.0&&!array_key_exists('discount',$payload), 'cobrança sem desconto preserva payload anterior');

$asaas = new AsaasDescontoFixoSpy();
$comAdicionais = adfCobranca(['COB_Valor'=>'110.00','COB_AdicionaisCentavos'=>2500]);
$asaas->criarCobranca($cliente, $comAdicionais);
$payload = $asaas->requests[0]['payload'];
adfAssert($payload['value']===125.0&&$payload['discount']['value']===15.0, 'adicionais integram nominal sem receber desconto de indicação');

$asaas = new AsaasDescontoFixoSpy();
$centavos = adfCobranca(['COB_Valor'=>'85.02','COB_ValorBaseCentavos'=>10001,'COB_AdicionaisCentavos'=>2,'COB_DescontoIndicacaoCentavos'=>1501]);
$asaas->criarCobranca($cliente, $centavos, 'cobranca_10_tentativa_1');
$asaas->criarCobranca($cliente, $centavos, 'cobranca_10_tentativa_2');
adfAssert($asaas->requests[0]['payload']['value']===100.03&&$asaas->requests[0]['payload']['discount']['value']===15.01, 'preserva centavos exatos na fronteira');
adfAssert($asaas->requests[0]['payload']['value']===$asaas->requests[1]['payload']['value']&&$asaas->requests[0]['payload']['discount']===$asaas->requests[1]['payload']['discount'], 'retry repete nominal e desconto congelados');

$asaasFonte = file_get_contents(__DIR__ . '/../app/Services/AsaasService.php');
$financeiroFonte = file_get_contents(__DIR__ . '/../app/Services/FinanceiroWorkflowService.php');
foreach([$asaasFonte,$financeiroFonte] as $fonte){
    adfAssert(strpos($fonte,'selecionarDisponiveisFifo')===false&&strpos($fonte,'ICR_Percentual')===false, 'fronteira financeira não calcula FIFO ou percentual');
}

echo "AsaasDescontoFixoTest OK\n";
