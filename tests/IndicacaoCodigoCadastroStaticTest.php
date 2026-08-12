<?php
$r=dirname(__DIR__);function icc($v,$m){if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
$ctl=file_get_contents($r.'/app/Controllers/SiteController.php');$view=file_get_contents($r.'/app/Views/site/cadastro.php');
icc(strpos($view,'name="codigo_indicacao"')!==false&&strpos($view,'Código de indicação')!==false,'campo opcional de indicação existe');
icc(strpos($view,"dadosCadastro['codigo_indicacao']")!==false&&strpos($view,'codigoIndicacao')!==false,'campo preserva post e preenche referência da sessão');
icc(strpos($ctl,'$_POST[\'codigo_indicacao\']')!==false&&strpos($ctl,'validarCodigoIndicacaoEnviado')!==false,'POST usa campo enviado como fonte de verdade');
icc(strpos($ctl,'Session::remove(self::SESSAO_CODIGO_INDICACAO);')!==false,'campo vazio remove referência da sessão');
icc(strpos($ctl,'validarCodigo($codigo)')!==false&&strpos($ctl,'ICD_CodigoNormalizado')!==false,'código digitado é validado e normalizado pelo domínio');
icc(substr_count($ctl,'Código de indicação inválido ou indisponível.')>=3,'código inválido mostra mensagem clara em URL e POST');
icc(strpos($ctl,"registrarIndicacao(")!==false&&strpos($ctl,"'manual'")!==false&&strpos($ctl,"'link'")!==false,'vínculo é delegado com origem adequada');
icc(strpos($ctl,'PDOException|\\DomainException')!==false&&strpos($ctl,'rollBack')!==false,'revalidação concorrente faz rollback do cadastro');
echo "IndicacaoCodigoCadastroStaticTest OK\n";
