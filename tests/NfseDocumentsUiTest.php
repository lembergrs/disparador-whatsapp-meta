<?php
require_once __DIR__ . '/../app/Core/Csrf.php';
require_once __DIR__ . '/../app/Core/Controller.php';
require_once __DIR__ . '/../app/Models/NfseEmissao.php';
require_once __DIR__ . '/../app/Controllers/FinanceiroController.php';

define('BASE_URL', 'https://app.invalid');
function documentUiAssert($condition, $message){ if(!$condition){ throw new RuntimeException($message); } }
$clientes = [];
$statusPermitidos = [];
$statusFiltro = '';
$emissoes = [];
foreach([
    [71, 'emitida', 'storage/nfse/xml/test.xml', null],
    [72, 'emitida', null, 'storage/nfse/pdf/legacy.pdf'],
    [73, 'cancelada', 'storage/nfse/xml/test.xml', null],
    [74, 'emitida', null, null]
] as [$id, $status, $xml, $pdf]){
    $emissoes[] = ['NFE_ID' => $id, 'CLI_ID' => 12, 'COB_ID' => $id + 100, 'NFE_Status' => $status, 'NFE_XmlStoragePath' => $xml, 'NFE_PdfStoragePath' => $pdf];
}
ob_start();
require __DIR__ . '/../app/Views/nfse/index.php';
$html = ob_get_clean();
$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
libxml_use_internal_errors($previous);
$xpath = new DOMXPath($dom);
foreach([71 => true, 72 => false, 73 => true, 74 => false] as $id => $hasXml){
    foreach(['nfse-master-row', 'nfse-child-row'] as $rowClass){
        $links = $xpath->query('//tr[contains(@class,"' . $rowClass . '")]//a[contains(@href,"nfse/pdf/' . $id . '")]');
        documentUiAssert($links->length === (int) $hasXml, "PDF da nota {$id} na {$rowClass} depende somente de XML");
    }
    $modal = $xpath->query('//*[@id="nfse-detalhes-' . $id . '"]')->item(0)->textContent;
    documentUiAssert((strpos($modal, 'gerado sob demanda') !== false) === $hasXml, 'detalhes mostram PDF sob demanda somente com XML');
    documentUiAssert(strpos(nfse_timeline($emissoes[array_search($id, array_column($emissoes, 'NFE_ID'))]), 'PDF') === false, 'timeline não inventa PDF armazenado');
}
documentUiAssert(strpos($html, 'storage/nfse/') === false, 'interface não expõe caminhos privados');

$db = new class($emissoes) {
    private $rows;
    public function __construct($rows){ $this->rows = $rows; }
    public function prepare($sql){
        documentUiAssert(strpos($sql, 'NFE_PdfStoragePath') === false, 'disponibilidade no financeiro não consulta PDF persistido');
        return new class($this->rows) {
            private $rows;
            public function __construct($rows){ $this->rows = $rows; }
            public function execute($params){ return true; }
            public function fetch($mode){ return array_shift($this->rows) ?: false; }
        };
    }
};
$vigentes = (new Models\NfseEmissao($db))->buscarVigentesPorCobrancas([171, 172, 173, 174], 12);
$financeiro = (new ReflectionClass(Controllers\FinanceiroController::class))->newInstanceWithoutConstructor();
$render = new ReflectionMethod($financeiro, 'renderNfseDocumentosCliente');
foreach([171 => true, 172 => false, 174 => false] as $cobrancaId => $hasXml){
    documentUiAssert($vigentes[$cobrancaId]['tem_pdf'] === $hasXml, 'modelo disponibiliza PDF pelo XML');
    $links = $render->invoke($financeiro, $vigentes[$cobrancaId]);
    documentUiAssert((strpos($links, 'nfse/pdf/') !== false) === $hasXml, 'financeiro apresenta PDF somente com XML');
}
echo "NFS-e document UI rendering tests passed\n";
