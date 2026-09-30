<?php
function prvAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$v=file_get_contents($r.'/app/Views/parceiros/cadastro_publico.php');
$s=file_get_contents($r.'/app/Services/ParceiroCadastroService.php');
prvAssert(strpos($v,"inputmask('99.999.999/9999-99')")!==false,'CNPJ deve ter mascara');
prvAssert(strpos($v,"inputmask('(99) 99999-9999')")!==false,'telefone deve ter mascara');
prvAssert(strpos($v,'cnpjValido')!==false,'CNPJ deve ser validado no navegador');
prvAssert(strpos($v,'telefoneValido')!==false,'telefone deve ser validado no navegador');
prvAssert(strpos($v,'emailValido')!==false && strpos($v,'toLowerCase()')!==false,'email deve ser validado e normalizado em minusculas');
prvAssert(strpos($v,'data-password-strength')!==false && strpos($v,'data-password-confirm="#senhaCadastroPartner"')!==false,'senha Partner deve usar componente padrao');
prvAssert(strpos($v,'password-strength.js')!==false,'formulario deve carregar componente de forca da senha');
prvAssert(strpos($s,"FILTER_VALIDATE_EMAIL")!==false,'backend deve validar email');
prvAssert(strpos($s,"strlen(\$telefone)!==10 && strlen(\$telefone)!==11")!==false,'backend deve validar telefone com DDD');
prvAssert(strpos($s,'DocumentoFiscalValidator::valido($cnpj)')!==false,'backend deve validar CNPJ');
prvAssert(strpos($s,'SenhaForteValidator::forte($senha)')!==false,'backend deve validar senha forte');
echo "Partner registration validation static tests passed\n";
