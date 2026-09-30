<?php
function pamAssert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$r=dirname(__DIR__);
$c=file_get_contents($r.'/app/Controllers/ApiV1Controller.php');
$s=file_get_contents($r.'/app/Services/PartnerMessageService.php');
$m=file_get_contents($r.'/app/Models/ParceiroApi.php');
$d=file_get_contents($r.'/docs/partner-api-v1.md');
pamAssert(strpos($c,'public function messages()')!==false,'controller deve expor messages');
pamAssert(strpos($c,"!== 'POST'")!==false,'messages deve aceitar somente POST');
pamAssert(strpos($c,"file_get_contents('php://input')")!==false,'messages deve ler JSON');
pamAssert(strpos($c,'PartnerApiAuthService')!==false,'endpoint deve autenticar API key');
pamAssert(strpos($s,'buscarCanalAutorizado')!==false,'serviço deve validar canal autorizado');
pamAssert(strpos($m,"pc.PAC_Status = 'ativo'")!==false,'canal deve estar ativo');
pamAssert(strpos($m,'PAC_FaturavelDesde IS NOT NULL')!==false,'canal deve estar faturável');
pamAssert(strpos($s,"['text','template']")!==false,'primeira versão deve limitar texto e template');
pamAssert(strpos($s,'ultimaMensagemRecebida')!==false && strpos($s,'86400')!==false,'texto deve respeitar janela de 24 horas');
pamAssert(strpos($s,'buscarAprovadoParaEnvioPorCliente')!==false,'template deve pertencer ao cliente e estar aprovado');
pamAssert(strpos($s,"(int)\$template['MTA_ID']!==\$metaId")!==false,'template deve pertencer ao canal');
pamAssert(strpos($s,"'origem'=>'partner_api'")!==false,'envio deve ser auditado na conversa');
pamAssert(strpos($s,"'status'=>'accepted'")!==false,'resposta deve retornar status estável');
pamAssert(strpos($d,'POST /index.php?url=api/v1/messages')!==false,'documentação deve conter endpoint');
echo "Partner messages static tests passed\n";
