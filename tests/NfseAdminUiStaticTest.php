<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/NfseController.php');
$view = file_get_contents($root . '/app/Views/nfse/index.php');
$menu = file_get_contents($root . '/app/Views/layouts/master.php');

function nfseUiAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

nfseUiAssert(strpos($controller, 'Auth::admin();') !== false, 'controller exige admin');
nfseUiAssert(substr_count($controller, '$this->validarCsrfPost();') >= 2, 'ações POST exigem CSRF');
nfseUiAssert(strpos($controller, 'mapearCobrancasElegiveisPorCliente') !== false, 'controller prepara mapa de cobranças por cliente');
nfseUiAssert(strpos($controller, "\$status !== 'pago'") !== false, 'backend filtra cobranças não pagas');
nfseUiAssert(strpos($controller, '$valor <= 0') !== false, 'backend filtra cobranças sem valor positivo');
nfseUiAssert(strpos($view, 'method="post"') !== false, 'emissão usa POST');
nfseUiAssert(strpos($view, 'Csrf::input()') !== false, 'view inclui CSRF');
nfseUiAssert(strpos($view, 'JSON_HEX_TAG') !== false && strpos($view, 'json_encode(') !== false, 'view serializa JSON com flags seguras');
nfseUiAssert(strpos($view, 'id="nfse_cobranca_id" class="form-control" required disabled') !== false, 'select cobrança inicia desabilitado');
nfseUiAssert(strpos($view, 'id="nfse_emitir_btn"') !== false && strpos($view, 'disabled onclick=') !== false, 'botão inicia desabilitado');
nfseUiAssert(strpos($view, 'Configuração fiscal incompleta') !== false, 'view avisa configuração fiscal incompleta');
nfseUiAssert(strpos($view, 'data-config-fiscal-completa') !== false, 'botão considera configuração fiscal antes de habilitar');
nfseUiAssert(strpos($view, 'Prévia fiscal para conferência') !== false, 'view mostra prévia fiscal ao administrador');
nfseUiAssert(strpos($view, 'function atualizarCobrancasPorCliente()') !== false, 'JS local atualiza cobranças por cliente');
nfseUiAssert(strpos($view, 'function atualizarEstadoBotaoEmissao()') !== false, 'JS local atualiza botão');
nfseUiAssert(strpos($view, 'limparCobrancas') !== false, 'troca de cliente limpa cobrança anterior');
nfseUiAssert(strpos($view, "form.addEventListener('submit'") !== false && strpos($view, "emitirBtn.disabled = true") !== false, 'submit previne duplo clique');
nfseUiAssert(strpos($view, 'Esta ação emitirá uma NFS-e real no ambiente configurado') !== false, 'confirmação forte de emissão real');
nfseUiAssert(strpos($view, 'htmlspecialchars') !== false, 'view escapa saída');
nfseUiAssert(strpos($view, 'url=nfse/pdf/') !== false && strpos($view, 'url=nfse/xml/') !== false, 'view usa downloads protegidos sem link direto para storage');
nfseUiAssert(strpos($view, 'storage/nfse') === false, 'view não expõe caminhos internos reais');
nfseUiAssert(strpos($menu, "url=nfse") !== false && strpos($menu, "usuario['nivel'] == 'admin'") !== false, 'menu NFS-e está no bloco admin');

echo "NFS-e admin UI static tests passed\n";
