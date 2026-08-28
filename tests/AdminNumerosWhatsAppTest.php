<?php

require_once __DIR__ . '/../app/Core/Auth.php';

use Core\Auth;

function adminMetaAssert($condicao, $mensagem){ if(!$condicao){ throw new RuntimeException($mensagem); } }

adminMetaAssert(Auth::podeGerenciarPropriaConfiguracaoMeta(['nivel'=>'admin','CLI_ID'=>38]), 'admin com CLI_ID válido pode gerenciar a própria configuração Meta');
adminMetaAssert(!Auth::podeGerenciarPropriaConfiguracaoMeta(['nivel'=>'admin','CLI_ID'=>0]), 'admin sem CLI_ID não pode gerenciar conexão Meta');
adminMetaAssert(Auth::podeGerenciarPropriaConfiguracaoMeta(['nivel'=>'cliente','CLI_ID'=>38]), 'cliente mantém acesso atual');
adminMetaAssert(Auth::podeGerenciarPropriaConfiguracaoMeta(['nivel'=>'cliente_admin','CLI_ID'=>38]), 'cliente_admin mantém acesso atual');
adminMetaAssert(!Auth::podeGerenciarPropriaConfiguracaoMeta(['nivel'=>'cliente_usuario','CLI_ID'=>38]), 'cliente_usuario permanece sem gestão de números');

$root = dirname(__DIR__);
$auth = file_get_contents($root . '/app/Core/Auth.php');
$controller = file_get_contents($root . '/app/Controllers/ConfiguracaoController.php');
$menu = file_get_contents($root . '/app/Views/layouts/master.php');

adminMetaAssert(strpos($auth, "['cliente', 'cliente_admin']") !== false, 'clientePodeConectarMeta preserva os níveis de pré-trial sem exceção para admin');
adminMetaAssert(strpos($menu, 'Auth::podeGerenciarPropriaConfiguracaoMeta($usuario)') !== false && strpos($menu, '/index.php?url=configuracao/meta') !== false, 'menu administrativo mostra Números WhatsApp diretamente no contexto próprio');
adminMetaAssert(strpos($menu, '<p>Contas Meta</p>') !== false && strpos($menu, '<p>Números WhatsApp</p>') !== false, 'Contas Meta e Números WhatsApp permanecem opções distintas');
adminMetaAssert(substr_count($controller, '$this->exigirGerenciamentoProprioMeta();') === 9, 'todas as nove ações próprias da tela Meta exigem a regra central');
adminMetaAssert(strpos($controller, 'listarPorCliente(') !== false && strpos($controller, "\$usuario['CLI_ID']") !== false, 'listagem permanece filtrada pelo CLI_ID da sessão');
adminMetaAssert(strpos($controller, 'avaliarLimiteNumerosPorCliente(') !== false && strpos($controller, '$clienteId,') !== false, 'limite continua avaliado pelo plano do CLI_ID associado');
adminMetaAssert(strpos($controller, 'atualizarAutoRespostaPorCliente(') !== false && strpos($controller, "(int) \$usuario['CLI_ID']") !== false, 'auto resposta permanece protegida pelo CLI_ID da sessão');
adminMetaAssert(strpos($controller, 'buscarPorCliente($contaId,$clienteId)') !== false && strpos($controller, 'buscarPorCliente($contaId, $clienteId)') !== false, 'ações operacionais continuam buscando contas no escopo do próprio cliente');

echo "AdminNumerosWhatsAppTest OK\n";
