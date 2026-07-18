<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/NotificacaoController.php');
$view = file_get_contents($root . '/app/Views/notificacoes/index.php');
$model = file_get_contents($root . '/app/Models/Notificacao.php');
$configModel = file_get_contents($root . '/app/Models/NotificacaoConfiguracao.php');
$service = file_get_contents($root . '/app/Services/NotificacaoService.php');
$formatador = file_get_contents($root . '/app/Services/NotificacaoFormatador.php');
$menu = file_get_contents($root . '/app/Views/layouts/master.php');
$migration = file_get_contents($root . '/database/migrations/20260718_create_notificacoes_configuracoes.sql');
$modelo = file_get_contents($root . '/app/Models/NotificacaoModelo.php');
$modeloMigration = file_get_contents($root . '/database/migrations/20260718_create_notificacoes_modelos.sql');
$email = file_get_contents($root . '/app/Services/EmailService.php');

function notifAdminAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }

notifAdminAssert(strpos($controller, 'Auth::admin();') !== false, 'controller exige admin no backend');
notifAdminAssert(strpos($controller, 'Csrf::exigirPost();') !== false, 'ações de escrita exigem CSRF');
notifAdminAssert(strpos($controller, 'new PHPMailer') === false && strpos($controller, 'PHPMailer') === false, 'controller não usa PHPMailer');
notifAdminAssert(strpos($controller, 'reenviarEmailAdmin') !== false, 'reenvio usa infraestrutura central');
notifAdminAssert(strpos($model, 'ORDER BY n.NOT_CriadoEm DESC, n.NOT_ID DESC') !== false, 'listagem ordena mais recentes primeiro');
notifAdminAssert(strpos($model, 'LEFT JOIN clientes') !== false, 'listagem exibe cliente por JOIN');
notifAdminAssert(strpos($model, 'marcarReenvioProcessando') !== false && strpos($model, "AND NOT_Status IN ('pendente','erro_temporario','erro_definitivo')") !== false, 'reenvio previne processamento duplicado');
notifAdminAssert(strpos($formatador, 'Boas-vindas') !== false && strpos($formatador, 'E-mail') !== false && strpos($formatador, 'Erro definitivo') !== false, 'descrições amigáveis centralizadas');
notifAdminAssert(strpos($formatador, 'Stack trace') !== false && strpos($formatador, '[mascarado]') !== false, 'detalhes mascaram dados sensíveis e stack trace');
notifAdminAssert(strpos($view, 'Nenhuma notificação foi registrada até o momento') !== false, 'estado vazio amigável existe');
notifAdminAssert(strpos($view, 'serverSide: true') !== false, 'DataTable server-side habilitado');
notifAdminAssert(strpos($view, 'Limpar filtros') !== false, 'limpar filtros existe');
notifAdminAssert(strpos($view, 'Esta ação enviará novamente') !== false && strpos($view, "prop('disabled', true)") !== false, 'reenvio confirma e bloqueia duplo clique');
notifAdminAssert(strpos($view, 'Em breve') !== false, 'canais indisponíveis desabilitados');
notifAdminAssert(strpos($configModel, 'EventoNotificacao::todos()') !== false, 'eventos vêm da estrutura central');
notifAdminAssert(strpos($configModel, 'canalImplementado($canal){ return $canal === CanalNotificacao::EMAIL; }') !== false, 'somente e-mail implementado editável');
notifAdminAssert(strpos($service, 'canaisEfetivos') !== false, 'NotificacaoService lê configuração efetiva central');
notifAdminAssert(strpos($migration, 'UNIQUE KEY uk_notificacoes_config_evento_canal') !== false, 'chave evento + canal é única');
notifAdminAssert(strpos($menu, 'url=notificacao') !== false && strpos($menu, "usuario['nivel'] == 'admin'") !== false, 'menu Notificações fica no bloco admin');

notifAdminAssert(strpos($controller, 'public function modelo()') !== false && strpos($controller, 'previewModelo') !== false && strpos($controller, 'salvarModelo') !== false && strpos($controller, 'restaurarModelo') !== false, 'controller possui rotas administrativas de modelos');
notifAdminAssert(strpos($controller, 'validarModeloPost') !== false && strpos($controller, 'placeholdersInvalidos') !== false, 'salvamento valida placeholders');
notifAdminAssert(strpos($controller, 'sanitizarHtml') !== false && strpos($controller, 'script|iframe|object|embed|form') !== false, 'backend sanitiza HTML perigoso');
notifAdminAssert(strpos($view, 'Editar modelo da notificação') !== false && strpos($view, 'js-editar-modelo') !== false, 'view possui ícone/modal de edição');
notifAdminAssert(strpos($view, 'Visualizar') !== false && strpos($view, 'previewFrame') !== false && strpos($view, 'sandbox=""') !== false, 'prévia usa iframe seguro sem enviar e-mail');
notifAdminAssert(strpos($view, 'Restaurar padrão') !== false && strpos($view, 'Esta ação removerá a personalização') !== false, 'restauração exige confirmação');
notifAdminAssert(strpos($view, 'Personalizado') !== false && strpos($view, 'Modelo padrão') !== false, 'indicador de modelo aparece');
notifAdminAssert(strpos($modeloMigration, 'CREATE TABLE IF NOT EXISTS notificacoes_modelos') !== false && strpos($modeloMigration, 'UNIQUE KEY uk_notificacoes_modelos_evento_canal') !== false, 'migration de modelos cria unique evento canal');
notifAdminAssert(strpos($modelo, 'ON DUPLICATE KEY UPDATE') !== false && strpos($modelo, 'NOM_Ativo') !== false, 'model persiste e restaura personalizações');
notifAdminAssert(strpos($email, 'buscarAtivo($evento, CanalNotificacao::EMAIL)') !== false && strpos($email, 'self::TEMPLATES[$evento] ?? null') !== false, 'EmailService usa modelo ativo com fallback');
notifAdminAssert(strpos($email, 'variaveisPorEvento') !== false && strpos($email, 'placeholdersInvalidos') !== false && strpos($email, 'dadosPreview') !== false, 'EmailService centraliza variáveis, validação e preview');


echo "NotificacaoAdminStaticTest OK\n";
