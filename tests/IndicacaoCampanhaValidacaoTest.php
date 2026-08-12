<?php

$root=dirname(__DIR__);
spl_autoload_register(function($classe)use($root){if(strpos($classe,'Services\\')===0||strpos($classe,'Models\\')===0){$arquivo=$root.'/app/'.str_replace('\\','/',$classe).'.php';if(is_file($arquivo))require $arquivo;}});
use Services\Indicacao\IndicacaoCampanhaService;

function icvt($v,$m){if(!$v)throw new RuntimeException($m);}
function icvtThrows(callable $f,$m){try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($m);}
$ref=new ReflectionClass(IndicacaoCampanhaService::class);$service=$ref->newInstanceWithoutConstructor();$validar=$ref->getMethod('validarDadosCriacao');$validar->setAccessible(true);
$base=['nome'=>'  Campanha válida  ','percentual'=>'15','data_inicio'=>'2026-08-12T10:30','data_fim'=>'2026-08-13T10:30','ativo'=>'S','publica'=>'S'];
icvtThrows(fn()=>$validar->invoke($service,array_merge($base,['nome'=>'   '])), 'nome vazio deve falhar');
icvtThrows(fn()=>$validar->invoke($service,array_merge($base,['data_inicio'=>'2026-99-99T10:30'])),'data inválida deve falhar');
icvtThrows(fn()=>$validar->invoke($service,array_merge($base,['data_inicio'=>'2026-08-14T10:30','data_fim'=>'2026-08-13T10:30'])),'fim anterior deve falhar');
$normalizado=$validar->invoke($service,$base);icvt($normalizado['nome']==='Campanha válida'&&$normalizado['data_inicio']==='2026-08-12 10:30:00'&&$normalizado['data_fim']==='2026-08-13 10:30:00','datetime-local deve normalizar');
echo "IndicacaoCampanhaValidacaoTest OK\n";
