<?php
$root = dirname(__DIR__);
$view = file_get_contents($root . '/app/Views/nfse/index.php');
$controller = file_get_contents($root . '/app/Controllers/NfseController.php');
$service = file_get_contents($root . '/app/Services/NfseEmissionService.php');
$model = file_get_contents($root . '/app/Models/NfseEmissao.php');
$doc = file_get_contents($root . '/docs/NFSE_ETAPA_3_OPERACIONAL.md');

function nfseOpAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }
function nfseOpHas($haystack, $needle, $msg){ nfseOpAssert(strpos($haystack, $needle) !== false, $msg . "\nMissing: {$needle}"); }

nfseOpHas($view, '<th>Data</th>', 'listagem exibe Data');
nfseOpHas($view, '<th>Cliente</th>', 'listagem exibe Cliente');
nfseOpHas($view, '<th>Cobrança</th>', 'listagem exibe Cobrança');
nfseOpHas($view, '<th>Valor</th>', 'listagem exibe Valor');
nfseOpHas($view, '<th>Status</th>', 'listagem exibe Status');
nfseOpHas($view, '<th>Documento</th>', 'listagem exibe Documento');
nfseOpHas($view, '<th>Ações</th>', 'listagem exibe Ações');
nfseOpHas($view, 'badge badge-success', 'status usa badge bootstrap/adminlte');
nfseOpHas($view, 'nfse_short', 'chave/requestId são abreviados');
nfseOpHas($view, 'data-copy', 'botão copiar chave/requestId disponível');
nfseOpHas($view, 'data-toggle="modal"', 'modal de detalhes/cancelamento disponível');
nfseOpHas($view, 'Confirmar cancelamento', 'cancelamento exige confirmação');
nfseOpHas($view, 'url=nfse/pdf/', 'download PDF autenticado por controller');
nfseOpHas($view, 'url=nfse/xml/', 'download XML autenticado por controller');
nfseOpHas($view, 'url=nfse/reconsultar', 'ação reconsultar disponível');
nfseOpHas($view, 'NFE_XmlStoragePath', 'view avalia disponibilidade de XML sem mostrar caminho');
nfseOpAssert(substr_count($view, 'NFE_XmlStoragePath') <= 2 && substr_count($view, 'NFE_PdfStoragePath') <= 2, 'paths internos não são exibidos como texto');
nfseOpHas($controller, 'public function pdf()', 'controller expõe rota protegida de PDF');
nfseOpHas($controller, 'public function xml()', 'controller expõe rota protegida de XML');
nfseOpHas($controller, 'Auth::admin();', 'downloads e ações exigem admin');
nfseOpHas($controller, 'public function reconsultar()', 'controller possui reconsulta');
nfseOpHas($controller, 'public function cancelar()', 'controller possui cancelamento');
nfseOpHas($service, 'consultarXmlManual', 'service consulta XML');
nfseOpHas($service, 'consultarEventosManual', 'service consulta eventos');
nfseOpHas($service, 'cancelarManual', 'service cancela manualmente');
nfseOpHas($service, 'arquivoDownload', 'service valida arquivo privado antes do download');
nfseOpHas($service, "registrarLogSeguro('consultar_pdf'", 'logs registram consultar_pdf');
nfseOpHas($service, "registrarLogSeguro('consultar_xml'", 'logs registram consultar_xml');
nfseOpHas($service, "registrarLogSeguro('consultar_eventos'", 'logs registram consultar_eventos');
nfseOpHas($service, "registrarLogSeguro('cancelar'", 'logs registram cancelar');
nfseOpHas($model, 'persistirRequestConsulta', 'model persiste requestId de consulta/cancelamento');
nfseOpHas($doc, 'Etapa 3', 'documentação da etapa operacional existe');

echo "NFS-e operational UI static tests passed\n";
