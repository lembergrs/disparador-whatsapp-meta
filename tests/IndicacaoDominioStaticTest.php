<?php

$root = dirname(__DIR__);
$sql = file_get_contents($root . '/database/migrations/20260807_create_programa_indicacao.sql');
$arquivos = [
    'app/Models/IndicacaoCampanha.php','app/Models/IndicacaoCodigo.php','app/Models/Indicacao.php',
    'app/Models/IndicacaoCredito.php','app/Models/IndicacaoAuditoria.php',
    'app/Services/Indicacao/IndicacaoCampanhaService.php','app/Services/Indicacao/IndicacaoCodigoService.php',
    'app/Services/Indicacao/IndicacaoService.php','app/Services/Indicacao/IndicacaoCreditoService.php',
    'app/Services/Indicacao/IndicacaoAuditoriaService.php','app/Services/Indicacao/IndicacaoStatusTransitionService.php',
    'app/Services/Indicacao/CodigoIndicacaoGeneratorInterface.php','app/Services/Indicacao/CodigoIndicacaoPadraoGenerator.php',
    'app/Services/Indicacao/CodigoIndicacaoNormalizer.php'
];
function assertTrue($c,$m){if(!$c){fwrite(STDERR,"FALHA: {$m}\n");exit(1);}}
foreach(['indicacao_campanhas','indicacao_codigos','indicacoes','indicacao_creditos','indicacao_auditoria'] as $t){assertTrue(str_contains($sql,"CREATE TABLE IF NOT EXISTS {$t}"),"tabela {$t}");}
assertTrue(!str_contains($sql,'COB_ID') && !str_contains($sql,'ASS_ID'),'crédito sem vínculo financeiro direto');
assertTrue(!str_contains($sql,'indicacao_credito_reservas'),'reservas fora desta sprint');
assertTrue(str_contains($sql,'uq_indicacao_campanha_publica_ativa'),'unicidade de campanha pública ativa');
assertTrue(str_contains($sql,'uq_indicacao_indicado'),'um indicador por indicado');
assertTrue(str_contains($sql,'uq_indicacao_credito_indicacao'),'um crédito por indicação');
foreach($arquivos as $a){assertTrue(is_file($root.'/'.$a),"arquivo {$a}");}
$gen=file_get_contents($root.'/app/Services/Indicacao/CodigoIndicacaoPadraoGenerator.php');
assertTrue(str_contains($gen,'random_bytes('),'geração criptográfica');
foreach(['rand(','mt_rand(','uniqid('] as $bad){assertTrue(!str_contains($gen,$bad),"não usar {$bad}");}
echo "IndicacaoDominioStaticTest: OK\n";
