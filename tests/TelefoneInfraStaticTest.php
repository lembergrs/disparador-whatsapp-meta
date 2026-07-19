<?php
$root = dirname(__DIR__);
$conversa = file_get_contents($root . '/app/Models/Conversa.php');
$contato = file_get_contents($root . '/app/Models/Contato.php');
$controller = file_get_contents($root . '/app/Controllers/ConversaController.php');
$view = file_get_contents($root . '/app/Views/conversas/index.php');
$migration = file_get_contents($root . '/database/migrations/20260718_add_telefone_normalizado.sql');
function telStaticAssert($c,$m){ if(!$c){ fwrite(STDERR,"FAIL: $m\n"); exit(1);} }
telStaticAssert(strpos($contato, 'TelefoneService::normalizar') !== false && strpos($contato, 'TelefoneService::variantes') !== false, 'Contato usa normalizacao central');
telStaticAssert(strpos($conversa, 'TelefoneService::normalizar') !== false && strpos($conversa, 'MTA_ID = ?') !== false && strpos($conversa, 'CVS_NumeroNormalizado') !== false, 'Conversa usa meta + telefone normalizado');
telStaticAssert(strpos($conversa, 'listarDuplicadasNormalizadas') !== false && strpos($conversa, 'unificarDuplicadas') !== false, 'rotina de unificacao existe');
telStaticAssert(strpos($conversa, 'INSERT IGNORE INTO conversa_etiqueta_vinculos') !== false, 'unificacao preserva etiquetas');
telStaticAssert(strpos($conversa, 'CON_Responsavel_USU_ID') !== false, 'unificacao trata responsavel');
telStaticAssert(strpos($controller, 'Auth::admin();') !== false && strpos($controller, 'unificarDuplicadas') !== false, 'admin controla unificacao');
telStaticAssert(strpos($view, 'Localizar conversas duplicadas') !== false && strpos($view, 'confirm(') !== false, 'tela administrativa de duplicadas existe');
telStaticAssert(strpos($migration, 'CON_TelefoneNormalizado') !== false && strpos($migration, 'CVS_NumeroNormalizado') !== false && strpos($migration, 'idx_conversas_meta_tel_norm') !== false, 'migration cria campos e indices');
echo "TelefoneInfraStaticTest OK\n";
