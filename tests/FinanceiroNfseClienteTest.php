<?php
$root = dirname(__DIR__);
$financeiro = file_get_contents($root . '/app/Controllers/FinanceiroController.php');
$view = file_get_contents($root . '/app/Views/financeiro/index.php');
$model = file_get_contents($root . '/app/Models/NfseEmissao.php');
$service = file_get_contents($root . '/app/Services/NfseEmissionService.php');
$nfseController = file_get_contents($root . '/app/Controllers/NfseController.php');
$doc = file_get_contents($root . '/docs/NFSE_ETAPA_3_OPERACIONAL.md');
$auth = file_get_contents($root . '/app/Core/Auth.php');

function financeiroNfseAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }
function financeiroNfseHas($haystack, $needle, $msg){ financeiroNfseAssert(strpos($haystack, $needle) !== false, $msg . "\nMissing: {$needle}"); }
function financeiroNfseNot($haystack, $needle, $msg){ financeiroNfseAssert(strpos($haystack, $needle) === false, $msg . "\nUnexpected: {$needle}"); }

financeiroNfseHas($financeiro, 'buscarVigentesPorCobrancas(', 'financeiro carrega NFS-e em lote, sem N+1');
financeiroNfseHas($financeiro, "array_column($" . "faturas, 'COB_ID')", 'financeiro usa IDs das cobranças paginadas');
financeiroNfseHas($financeiro, '$clienteId', 'financeiro filtra pelo CLI_ID da sessão');
financeiroNfseHas($financeiro, 'renderNfseStatusCliente', 'financeiro renderiza status fiscal simples');
financeiroNfseHas($financeiro, 'renderNfseDocumentosCliente', 'financeiro renderiza documentos fiscais');
financeiroNfseHas($financeiro, 'Não emitida', 'cobrança sem emissão mostra não emitida');
financeiroNfseHas($financeiro, "'pendente' => ['Pendente'", 'emissão pendente mostra pendente');
financeiroNfseHas($financeiro, "'processando' => ['Emitindo'", 'emissão processando mostra emitindo');
financeiroNfseHas($financeiro, "'emitida' => ['Emitida'", 'emissão emitida mostra emitida');
financeiroNfseHas($financeiro, "'cancelada' => ['Cancelada'", 'emissão cancelada mostra cancelada');
financeiroNfseHas($financeiro, 'Processando nota fiscal', 'erro temporário não expõe detalhe técnico');
financeiroNfseHas($financeiro, 'Nota fiscal pendente', 'erro definitivo não expõe detalhe técnico');
financeiroNfseHas($financeiro, 'nfse/pdf/', 'PDF usa rota protegida');
financeiroNfseHas($financeiro, 'nfse/xml/', 'XML usa rota protegida');
financeiroNfseNot($financeiro, 'NFE_RetornoSanitizado', 'financeiro não envia retorno técnico para view');
financeiroNfseNot($financeiro, 'NFE_UltimoErroMensagem', 'financeiro não exibe erro técnico');
financeiroNfseNot($financeiro, 'NFE_RequestId', 'financeiro não exibe RequestId');
financeiroNfseNot($financeiro, 'NFE_NumDps', 'financeiro não exibe numDPS');
financeiroNfseNot($financeiro, 'storage/nfse', 'financeiro não linka storage diretamente');

financeiroNfseHas($view, '<th>NFS-e</th>', 'view possui coluna NFS-e');
financeiroNfseHas($view, '<th>Documentos</th>', 'view possui coluna documentos');
financeiroNfseHas($view, 'colspan="8"', 'view ajusta colspan sem duplicar cobrança');
financeiroNfseNot($view, 'storage/nfse', 'view não contém link direto para storage');
financeiroNfseNot($view, 'RequestId', 'view não menciona RequestId ao cliente');
financeiroNfseNot($view, 'timeline', 'view financeiro não exibe timeline fiscal');

financeiroNfseHas($model, 'function buscarVigentesPorCobrancas(array $cobrancaIds', 'model expõe busca vigente agrupada');
financeiroNfseHas($model, "CASE WHEN NFE_Status <> 'cancelada' THEN 0 ELSE 1 END", 'model prioriza emissão ativa antes de cancelada');
financeiroNfseHas($model, 'NFE_ID DESC', 'model escolhe cancelada mais recente quando necessário');
financeiroNfseHas($model, "'tem_pdf' => !empty($" . "row['NFE_PdfStoragePath'])", 'model retorna apenas existência de PDF');
financeiroNfseHas($model, "'tem_xml' => !empty($" . "row['NFE_XmlStoragePath'])", 'model retorna apenas existência de XML');
financeiroNfseNot($model, "'NFE_RetornoSanitizado' =>", 'model não retorna retorno sanitizado para financeiro');

financeiroNfseHas($nfseController, 'Auth::check();', 'download permite usuário autenticado e delega autorização ao service');
financeiroNfseHas($auth, "if($" . "controller == 'nfse')", 'Auth trata NFS-e de forma específica');
financeiroNfseHas($auth, "return in_array($" . "metodo, ['pdf', 'xml'], true);", 'somente downloads pdf/xml passam pelo bloqueio financeiro');
financeiroNfseNot($auth, "'nfse',", 'Auth não libera todo o controller nfse para cliente');
financeiroNfseHas($service, 'usuarioPodeBaixarArquivo', 'service valida autorização de download');
financeiroNfseHas($service, "($" . "usuario['nivel'] ?? '') === 'admin'", 'administrador continua autorizado');
financeiroNfseHas($service, "['cliente', 'cliente_admin', 'cliente_usuario']", 'cliente autenticado pode ser avaliado');
financeiroNfseHas($service, "(int) ($" . "info['CLI_ID'] ?? 0) !== $" . "clienteId", 'cliente não baixa NFS-e de outro CLI_ID');
financeiroNfseHas($service, "($" . "cobranca['CLI_ID'] ?? 0) === $" . "clienteId", 'download valida vínculo da cobrança');
financeiroNfseHas($service, 'Documento fiscal não encontrado.', 'acesso indevido não revela existência de documento');

financeiroNfseHas($doc, 'Disponibilização ao cliente', 'documentação descreve disponibilização ao cliente');
financeiroNfseHas($doc, 'status são simplificados', 'documentação descreve status simples');
financeiroNfseHas($doc, 'rotas autenticadas `nfse/pdf/{id}` e `nfse/xml/{id}`', 'documentação descreve downloads protegidos');

// Cenários cobertos estruturalmente: sem emissão, pendente, processando, emitida com/sem PDF, cancelada,
// cancelada seguida de nova ativa, ausência de duplicação, autorização owner/admin e bloqueio de outro CLI_ID.
echo "Financeiro NFS-e cliente static tests passed\n";
