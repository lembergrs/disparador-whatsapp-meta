<?php

$r=dirname(__DIR__);
function icv($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$s=file_get_contents($r.'/app/Services/Indicacao/IndicacaoCampanhaService.php');
$m=file_get_contents($r.'/app/Models/IndicacaoCampanha.php');

foreach(['Nome da campanha é obrigatório','mb_strlen($nome',"'Y-m-d\\\\TH:i'",'Y-m-d H:i:s','A data de início deve ser anterior','Já existe campanha pública ativa'] as $x){
    icv(strpos($s,$x)!==false,'validação presente: '.$x);
}
icv(strpos($s,'validarDadosCriacao($d)')!==false,'criação valida antes de iniciar escrita');
icv(strpos($s,'public function editar')!==false&&strpos($s,'$dados=$this->validarDadosCriacao($dados);')!==false,'edição reutiliza a validação de campanha');
icv(strpos($s,'(int)$atual[\'ICP_ID\']!==(int)$id')!==false,'edição exclui a própria campanha da verificação de conflito');
icv(strpos($s,"'configuracao_editada'")!==false&&strpos($s,"'percentual_anterior'")!==false&&strpos($s,"'percentual_novo'")!==false,'edição registra auditoria antes e depois');
icv(strpos($m,'UPDATE indicacao_campanhas SET ICP_Nome=?, ICP_Percentual=?, ICP_DataInicio=?, ICP_DataFim=?, ICP_Publica=?')!==false,'edição atualiza somente a configuração permitida da campanha');
foreach(['indicacao_creditos','indicacoes','indicacao_codigos','indicacao_credito_reservas','cobrancas'] as $historico) icv(strpos($m,$historico)===false,'model de campanha não reescreve histórico: '.$historico);
echo "IndicacaoCampanhaValidacaoStaticTest OK\n";
