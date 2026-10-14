<?php
function lcAssert($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}}
$view=file_get_contents(dirname(__DIR__).'/app/Views/conversas/partials/mensagens.php');
lcAssert(strpos($view,"$"."tipo === 'location'")!==false,'cartao somente para localizacao');
lcAssert(strpos($view,"strpos($"."texto, '[LOCATION] ') === 0")!==false,'cartao exige payload de localizacao');
lcAssert(strpos($view,'json_decode(')!==false,'cartao interpreta JSON');
lcAssert(strpos($view,'is_finite($lat)')!==false && strpos($view,'$lat >= -90')!==false && strpos($view,'$lng <= 180')!==false,'cartao valida coordenadas');
lcAssert(strpos($view,'rawurlencode(')!==false,'URL do mapa deve codificar coordenadas');
lcAssert(strpos($view,'htmlspecialchars(')!==false && strpos($view,'$escape($nome)')!==false && strpos($view,'$escape($endereco)')!==false,'dados de localizacao devem ser escapados');
lcAssert(strpos($view,'rel="noopener noreferrer"')!==false,'link externo deve ser protegido');
lcAssert(strpos($view,'return;')!==false,'cartao nao deve duplicar JSON');
echo "Conversation location card static tests passed\n";
