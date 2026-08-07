<?php
$r=dirname(__DIR__);function isa($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$m=file_get_contents($r.'/database/migrations/20260807_create_programa_indicacao.sql');
foreach(['indicacao_campanhas','indicacao_codigos','indicacoes','indicacao_creditos','indicacao_auditoria'] as $t)isa(strpos($m,"CREATE TABLE IF NOT EXISTS $t")!==false,"migration contém $t");
isa(strpos($m,'indicacao_credito_reservas')===false,'não cria reservas financeiras');isa(strpos($m,'COB_ID')===false&&strpos($m,'ASS_ID')===false,'crédito não referencia cobrança/assinatura');isa(strpos($m,'uk_indicacao_campanha_publica_ativa')!==false,'unicidade pública ativa no banco');isa(strpos($m,'uk_indicacao_codigo_normalizado')!==false&&strpos($m,'uk_indicacao_indicado')!==false&&strpos($m,'uk_indicacao_credito_indicacao')!==false,'constraints de duplicidade');
$gen=file_get_contents($r.'/app/Services/Indicacao/CodigoIndicacaoPadraoGenerator.php');isa(strpos($gen,'random_bytes')!==false&&!preg_match('/\b(rand|mt_rand|uniqid)\s*\(/',$gen),'geração criptograficamente segura');
foreach(['IndicacaoCampanha','IndicacaoCodigo','Indicacao','IndicacaoCredito','IndicacaoAuditoria'] as $n)isa(is_file("$r/app/Models/$n.php"),"model $n");foreach(['IndicacaoCampanhaService','IndicacaoCodigoService','IndicacaoService','IndicacaoCreditoService','IndicacaoAuditoriaService'] as $n)isa(is_file("$r/app/Services/$n.php"),"service $n");
$all='';foreach(array_merge(glob($r.'/app/Models/Indicacao*.php'),glob($r.'/app/Services/Indicacao*.php'),glob($r.'/app/Services/Indicacao/*.php')) as $f)$all.=file_get_contents($f);foreach(['eval(','unserialize(','shell_exec(','passthru('] as $bad)isa(strpos($all,$bad)===false,"sem $bad");
echo "IndicacaoDominioStaticTest OK\n";
