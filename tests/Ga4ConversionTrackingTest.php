<?php
require_once __DIR__.'/../app/Services/AnalyticsService.php';

use Services\AnalyticsService;

function ga4ConversionAssert($condition,$message){ if(!$condition){ fwrite(STDERR,"FAIL: {$message}\n"); exit(1); } }

if(session_status()!==PHP_SESSION_ACTIVE) session_start();
$_SESSION=[];
ga4ConversionAssert(AnalyticsService::registrar('cadastro_concluido',['email'=>'privado@example.test','telefone'=>'5511999999999'])===true,'evento concluído deve ser aceito');
ga4ConversionAssert(AnalyticsService::registrar('cadastro_concluido',['cpf'=>'123'])===true,'registro repetido deve ser aceito e deduplicado');
$fila=AnalyticsService::consumir();
ga4ConversionAssert(count($fila)===1&&$fila[0]['evento']==='cadastro_concluido'&&$fila[0]['dados']===[],'evento deve ser one-shot e não conter PII');
ga4ConversionAssert(AnalyticsService::consumir()===[],'refresh não pode reenviar evento backend consumido');

$root=dirname(__DIR__);
$site=file_get_contents($root.'/app/Controllers/SiteController.php');
$cadastro=file_get_contents($root.'/app/Views/site/cadastro.php');
$analytics=file_get_contents($root.'/app/Services/AnalyticsService.php');
$gtm=file_get_contents($root.'/app/Views/partials/google_tag_manager.php');

$commit=strpos($site,'$db->commit();');
$concluido=strpos($site,"AnalyticsService::registrar('cadastro_concluido')");
$catch=strpos($site,'catch (PDOException|\\DomainException $e)');
ga4ConversionAssert($commit!==false&&$concluido>$commit&&$concluido<$catch,'cadastro concluído deve ocorrer somente depois do commit e fora dos erros');
ga4ConversionAssert(substr_count($site,"AnalyticsService::registrar('cadastro_concluido')")===1,'conclusão deve ter um único ponto de emissão');
ga4ConversionAssert(strpos($cadastro,"analytics.push('inicio_cadastro')")!==false,'início deve ocorrer na primeira interação com formulário');
ga4ConversionAssert(strpos($cadastro,"sessionStorage.getItem(chaveInicioCadastro)")!==false&&strpos($cadastro,"sessionStorage.setItem(chaveInicioCadastro, '1')")!==false,'início deve resistir a reload na mesma aba');
ga4ConversionAssert(substr_count($cadastro,"analytics.push('inicio_cadastro')")===1,'início deve ter um único ponto de emissão');
ga4ConversionAssert(strpos($analytics,"'cadastro_concluido' => []")!==false,'conclusão não deve aceitar parâmetros');
ga4ConversionAssert(strpos($cadastro,"analytics.push('inicio_cadastro',")===false,'início não deve enviar parâmetros ou PII');
ga4ConversionAssert(substr_count($gtm,'googletagmanager.com/gtm.js')===1&&strpos($gtm,'gtag(')===false,'não deve existir segunda instalação GA4');
ga4ConversionAssert(strpos($cadastro,"analytics.push('sign_up_start'")!==false&&strpos($site,"AnalyticsService::registrar('sign_up'")!==false,'eventos anteriores devem permanecer');

echo "Ga4ConversionTrackingTest OK\n";
