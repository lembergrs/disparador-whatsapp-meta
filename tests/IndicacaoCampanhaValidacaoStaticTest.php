<?php

$r=dirname(__DIR__);
function icv($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$s=file_get_contents($r.'/app/Services/Indicacao/IndicacaoCampanhaService.php');

foreach(['Nome da campanha é obrigatório','mb_strlen($nome',"'Y-m-d\\\\TH:i'",'Y-m-d H:i:s','A data de início deve ser anterior','Já existe campanha pública ativa'] as $x){
    icv(strpos($s,$x)!==false,'validação presente: '.$x);
}
icv(strpos($s,'validarDadosCriacao($d)')!==false,'criação valida antes de iniciar escrita');
echo "IndicacaoCampanhaValidacaoStaticTest OK\n";
