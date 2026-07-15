<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/NfseController.php');
$view = file_get_contents($root . '/app/Views/nfse/index.php');
$menu = file_get_contents($root . '/app/Views/layouts/master.php');

function nfseUiAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

nfseUiAssert(strpos($controller, 'Auth::admin();') !== false, 'controller exige admin');
nfseUiAssert(substr_count($controller, '$this->validarCsrfPost();') >= 2, 'ações POST exigem CSRF');
nfseUiAssert(strpos($controller, "
    public function emitir()") !== false && strpos($controller, "
    public function consultarPdf()") !== false, 'ações administrativas existem');
nfseUiAssert(strpos($view, 'method="post"') !== false, 'emissão usa POST');
nfseUiAssert(strpos($view, 'Csrf::input()') !== false, 'view inclui CSRF');
nfseUiAssert(strpos($view, "COB_Status'] ?? ''") !== false, 'view filtra status da cobrança');
nfseUiAssert(strpos($view, 'Esta ação emitirá uma NFS-e real no ambiente configurado') !== false, 'confirmação forte de emissão real');
nfseUiAssert(strpos($view, 'htmlspecialchars') !== false, 'view escapa saída');
nfseUiAssert(strpos($view, 'NFE_XmlStoragePath') === false && strpos($view, 'NFE_PdfStoragePath') === false, 'view não expõe caminhos internos');
nfseUiAssert(strpos($menu, "url=nfse") !== false && strpos($menu, "usuario['nivel'] == 'admin'") !== false, 'menu NFS-e está no bloco admin');

echo "NFS-e admin UI static tests passed\n";
