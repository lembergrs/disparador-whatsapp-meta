<?php
require_once __DIR__ . '/../app/Services/TelefoneService.php';
use Services\TelefoneService;
function telAssert($c,$m){ if(!$c){ fwrite(STDERR,"FAIL: $m\n"); exit(1);} }
telAssert(TelefoneService::normalizar('+55 (41) 99812-1080') === '5541998121080', 'remove mascara espacos e mais');
telAssert(TelefoneService::normalizar('41 99812-1080') === '5541998121080', 'celular 9 digitos canonico');
telAssert(TelefoneService::normalizar('4198121080') === '5541998121080', 'celular 8 digitos ganha nono');
telAssert(TelefoneService::normalizar('4133334444') === '554133334444', 'fixo nao ganha nono');
telAssert(TelefoneService::normalizar('(41)99812-1080 abc') === '5541998121080', 'remove invalidos');
$v = TelefoneService::variantes('41998121080');
telAssert(in_array('5541998121080',$v,true) && in_array('554198121080',$v,true), 'variantes incluem com e sem nono');
echo "TelefoneServiceTest OK\n";
