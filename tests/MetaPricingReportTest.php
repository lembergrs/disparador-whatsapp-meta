<?php
function pricingReportAssert($condicao,$mensagem){ if(!$condicao){ fwrite(STDERR,"FAIL: {$mensagem}\n"); exit(1); } }

function pricingScore(array $linha){
    $score=0;
    foreach(['categoria','billable','model','type','market','currency'] as $campo) if($linha[$campo]!==null) $score++;
    return $score;
}

function pricingCanonicas(array $linhas,$inicio='0000-01-01 00:00:00',$fimExclusivo='9999-12-31 00:00:00'){
    $grupos=[];
    foreach($linhas as $linha){
        if($linha['direcao']!=='enviada'||$linha['wamid']===null||$linha['wamid']==='') continue;
        $grupos[$linha['mta'].':'.$linha['wamid']][]=$linha;
    }
    $resultado=[];
    foreach($grupos as $chave=>$grupo){
        usort($grupo,function($a,$b){
            if(pricingScore($a)!==pricingScore($b)) return pricingScore($b)<=>pricingScore($a);
            $atualizado=strcmp($b['atualizado']??'1000-01-01 00:00:00',$a['atualizado']??'1000-01-01 00:00:00');
            return $atualizado!==0?$atualizado:$b['id']<=>$a['id'];
        });
        $canonica=$grupo[0];
        if($canonica['data']>=$inicio&&$canonica['data']<$fimExclusivo) $resultado[$chave]=$canonica;
    }
    return $resultado;
}

function linha($id,$wamid,$opcoes=[]){
    return array_merge(['id'=>$id,'mta'=>1,'wamid'=>$wamid,'direcao'=>'enviada','billable'=>null,'categoria'=>null,'model'=>null,'type'=>null,'market'=>null,'currency'=>null,'atualizado'=>'2026-08-01 10:00:00','data'=>'2026-08-10 10:00:00'],$opcoes);
}

$model=file_get_contents(__DIR__.'/../app/Models/MetaPricingReport.php');
$controller=file_get_contents(__DIR__.'/../app/Controllers/MetaPricingReportController.php');
$view=file_get_contents(__DIR__.'/../app/Views/meta_pricing_report/index.php');
$menu=file_get_contents(__DIR__.'/../app/Views/layouts/master.php');
$doc=file_get_contents(__DIR__.'/../docs/meta-pricing-report.md');

pricingReportAssert(strpos($model,'ROW_NUMBER() OVER')!==false && strpos($model,'CanonicalRank=1')!==false,'deduplicação deve selecionar uma linha canônica');
pricingReportAssert(strpos($model,'PARTITION BY c.MTA_ID,m.MSG_MetaMessageId')!==false,'chave canônica deve ser MTA_ID + wamid');
pricingReportAssert(strpos($model,'SELECT m.MSG_ID, ROW_NUMBER() OVER')!==false && strpos($model,'INNER JOIN conversa_mensagens m ON m.MSG_ID=candidatas.MSG_ID')!==false,'window deve ser estreita e carregar os campos somente após escolher o MSG_ID');
pricingReportAssert(strpos($model,'m.MSG_PricingBillable IS NOT NULL')!==false,'billable zero deve contar na completude');
pricingReportAssert(strpos($model,'m.MSG_AtualizadoEm DESC, m.MSG_ID DESC')!==false,'atualização e MSG_ID devem desempatar');
pricingReportAssert(strpos($model,'MAX(m.MSG_PricingBillable)')===false && strpos($model,'GROUP BY c.MTA_ID,m.MSG_MetaMessageId')===false,'campos não podem ser agregados independentemente');
pricingReportAssert(strpos($model,"m.MSG_Direcao='enviada'")!==false && strpos($model,"m.MSG_MetaMessageId IS NOT NULL")!==false,'somente mensagens enviadas com wamid entram');
pricingReportAssert(strpos($model,"DataHora>=?")!==false && strpos($model,"DataHora<?")!==false,'período deve ser semiaberto e pós-canônico');
pricingReportAssert(strpos($model,'Categoria=?')!==false && strpos($model,'Billable=?')!==false,'categoria e billable devem ser filtros pós-canônicos parametrizados');
pricingReportAssert(strpos($model,'Billable=0')!==false && strpos($model,'Billable IS NULL')!==false,'métricas de billable devem ser distintas');
pricingReportAssert(strpos($model,'GROUP BY Categoria')!==false && strpos($model,'GROUP BY PricingType')!==false,'resumos devem ser dinâmicos');
pricingReportAssert(substr_count($controller,'Auth::admin()')>=2,'tela e endpoint devem exigir administrador');
pricingReportAssert(strpos($controller,"modify('-365 days')")!==false && strpos($controller,"modify('+1 day')")!==false,'período deve ter até 366 dias e fim exclusivo');
pricingReportAssert(strpos($controller,"trim((string)\$busca)===''?\$total")!==false,'DataTable sem busca deve reutilizar recordsTotal');
pricingReportAssert(strpos($view,'serverSide:true')!==false && strpos($view,'período foi ajustado')!==false,'DataTable deve ser server-side e ajuste deve ser amigável');
pricingReportAssert(strpos($menu,'url=metaPricingReport')!==false,'menu administrativo deve expor o relatório');
pricingReportAssert(strpos($doc,'mesma linha')!==false && strpos($doc,'semiaberto')!==false,'documentação deve descrever linha física e período');

$tuple=pricingCanonicas([
    linha(10,'T',['categoria'=>'utility','billable'=>1,'type'=>'regular']),
    linha(11,'T',['categoria'=>'service','billable'=>0,'model'=>'CBP','type'=>'free_customer_service','market'=>'BR'])
]);
pricingReportAssert($tuple['1:T']['categoria']==='service'&&$tuple['1:T']['billable']===0&&$tuple['1:T']['type']==='free_customer_service','tuple incompatível deve vir integralmente da linha mais completa');

$completude=pricingCanonicas([linha(20,'C',['categoria'=>'utility']),linha(19,'C',['categoria'=>'service','billable'=>0])]);
pricingReportAssert($completude['1:C']['id']===19,'linha mais completa deve prevalecer mesmo com MSG_ID menor');

$recente=pricingCanonicas([linha(30,'R',['categoria'=>'utility','atualizado'=>'2026-08-01 10:00:00']),linha(29,'R',['categoria'=>'service','atualizado'=>'2026-08-02 10:00:00'])]);
pricingReportAssert($recente['1:R']['id']===29,'mais recentemente atualizada deve vencer com mesma completude');

$desempate=pricingCanonicas([linha(40,'D',['categoria'=>'utility']),linha(41,'D',['categoria'=>'service'])]);
pricingReportAssert($desempate['1:D']['id']===41,'maior MSG_ID deve ser desempate final');

$billable=pricingCanonicas([linha(50,'B',['billable'=>1]),linha(51,'B',['billable'=>0,'categoria'=>'service'])]);
pricingReportAssert($billable['1:B']['billable']===0,'billable canônico zero não pode ser substituído por um antigo igual a um');

$periodo=pricingCanonicas([linha(60,'P',['categoria'=>'utility','data'=>'2026-08-15 10:00:00']),linha(61,'P',['categoria'=>'service','billable'=>0,'data'=>'2026-09-01 10:00:00'])],'2026-08-01 00:00:00','2026-09-01 00:00:00');
pricingReportAssert(!isset($periodo['1:P']),'linha canônica deve ser escolhida antes de aplicar o período');

$limite=pricingCanonicas([linha(70,'L1',['data'=>'2026-08-31 23:59:59']),linha(71,'L2',['data'=>'2026-09-01 00:00:00'])],'2026-08-01 00:00:00','2026-09-01 00:00:00');
pricingReportAssert(isset($limite['1:L1'])&&!isset($limite['1:L2']),'intervalo semiaberto deve incluir todo o último dia e excluir a meia-noite seguinte');

$metricas=pricingCanonicas([linha(80,'M0',['billable'=>0]),linha(81,'M1',['billable'=>1]),linha(82,'MN')]);
$total=count($metricas); $comPricing=$faturaveis=$naoFaturaveis=$semInformacao=0;
foreach($metricas as $item){ if(pricingScore($item)>0)$comPricing++; if($item['billable']===1)$faturaveis++; elseif($item['billable']===0)$naoFaturaveis++; else $semInformacao++; }
pricingReportAssert($comPricing===2,'billable zero sozinho deve contar como pricing presente');
pricingReportAssert($faturaveis+$naoFaturaveis+$semInformacao===$total,'partições de billable devem somar o total');

echo "MetaPricingReportTest OK\n";
