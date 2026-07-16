<?php
$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/20260716_allow_nfse_reemission_after_cancel.sql');
$model = file_get_contents($root . '/app/Models/NfseEmissao.php');
$service = file_get_contents($root . '/app/Services/NfseEmissionService.php');
$auth = file_get_contents($root . '/app/Core/Auth.php');
$financeiro = file_get_contents($root . '/app/Controllers/FinanceiroController.php');
$nfseController = file_get_contents($root . '/app/Controllers/NfseController.php');

function nfseAuditAssert($cond, $msg){ if(!$cond){ fwrite(STDERR, "FAIL: {$msg}\n"); exit(1); } }
function nfseAuditHas($haystack, $needle, $msg){ nfseAuditAssert(strpos($haystack, $needle) !== false, $msg . "\nMissing: {$needle}"); }
function nfseAuditNot($haystack, $needle, $msg){ nfseAuditAssert(strpos($haystack, $needle) === false, $msg . "\nUnexpected: {$needle}"); }

nfseAuditHas($migration, 'ADD COLUMN NFE_EmissaoAtiva TINYINT(1) NULL DEFAULT 1', 'migration cria flag nullable com default ativo');
nfseAuditHas($migration, 'SET NFE_EmissaoAtiva = NULL', 'migration marca canceladas como NULL');
nfseAuditHas($migration, 'WHERE NFE_Status = \'cancelada\'', 'migration só inativa canceladas existentes');
nfseAuditHas($migration, 'ADD UNIQUE KEY uk_nfse_cobranca_ativa (COB_ID, NFE_EmissaoAtiva)', 'constraint limita uma ativa por cobrança e permite múltiplos NULL em MariaDB');
nfseAuditHas($migration, 'DROP INDEX uk_nfse_cobranca', 'constraint antiga por COB_ID é removida');

nfseAuditHas($model, 'NFE_EmissaoAtiva, NFE_DataReserva', 'novas emissões informam a flag ativa');
nfseAuditHas($model, ':prestador_cnpj, :ambiente, :competencia, :valor, :descricao, :serie, 1, NOW()', 'nova emissão nasce ativa com NFE_EmissaoAtiva = 1');
nfseAuditNot($model, "defined('NFSE_AMBIENTE') ? NFSE_AMBIENTE : 'production'", 'model não inventa fallback de ambiente fiscal');
nfseAuditNot($model, "defined('NFSE_DPS_SERIE') ? NFSE_DPS_SERIE : '900'", 'model não inventa série fiscal');
nfseAuditHas($model, "NFE_Status <> 'cancelada' ORDER BY NFE_ID DESC LIMIT 1", 'busca ativa ignora canceladas');
nfseAuditHas($model, 'CASE WHEN NFE_Status <> \'cancelada\' THEN 0 ELSE 1 END', 'vigente prioriza não cancelada sobre cancelada');
nfseAuditHas($model, 'NFE_ID DESC', 'vigente escolhe registro mais recente dentro da prioridade');
nfseAuditHas($model, '$existente = $this->buscarPorCobranca($cobrancaId);', 'em concorrência após duplicidade, busca ativa vencedora é retornada');
nfseAuditHas($model, 'Migration de reemissão de NFS-e pendente', 'falha de migration ausente fica explícita');
nfseAuditHas($model, 'function prepararContextoFiscalAntesDaReserva', 'model possui reparo controlado de contexto antes da reserva');
nfseAuditHas($model, 'AND NFE_NumDps IS NULL', 'reparo só ocorre sem numDPS');
nfseAuditHas($model, 'AND NFE_RequestIdEmissao IS NULL', 'reparo só ocorre sem RequestId');
nfseAuditHas($model, 'NFE_Status IN (:pendente_dados, :pendente, :erro_temporario, :erro_definitivo)', 'reparo só ocorre em status preparável');

nfseAuditHas($model, 'NFE_EmissaoAtiva = NULL, NFE_DataCancelamento = NOW()', 'cancelamento inativa a emissão na mesma atualização lógica');
nfseAuditHas($service, 'usuarioPodeBaixarArquivo', 'download é autorizado no service');
nfseAuditHas($service, "($" . "usuario['nivel'] ?? '') === 'admin'", 'admin segue autorizado para documentos históricos');
nfseAuditHas($service, "($" . "info['CLI_ID'] ?? 0) !== $" . "clienteId", 'cliente não acessa emissão de outro CLI_ID');
nfseAuditHas($service, "($" . "cobranca['CLI_ID'] ?? 0) === $" . "clienteId", 'cliente não acessa documento alterando ID se cobrança não for sua');

nfseAuditHas($auth, "if($" . "controller == 'nfse')", 'Auth trata rota nfse especificamente');
nfseAuditHas($auth, "return in_array($" . "metodo, ['pdf', 'xml'], true);", 'Auth libera somente pdf/xml para cliente bloqueado financeiramente');
nfseAuditNot($auth, "'nfse',", 'Auth não libera index/emitir/reconsultar/cancelar por controller inteiro');
nfseAuditHas($nfseController, 'public function index()', 'controller tem index administrativo');
nfseAuditHas($nfseController, 'Auth::admin();', 'ações administrativas continuam exigindo admin');
nfseAuditHas($nfseController, 'Auth::check();', 'downloads exigem autenticação antes do service');

nfseAuditHas($financeiro, 'buscarVigentesPorCobrancas(', 'Financeiro só consulta NFS-e vigente em lote');
nfseAuditNot($financeiro, 'criarOuBuscarPorCobranca(', 'abrir Financeiro não cria emissão');
nfseAuditNot($financeiro, 'reservar(', 'abrir Financeiro não reserva DPS');
nfseAuditNot($financeiro, '->emitir(', 'abrir Financeiro não chama API de emissão');
nfseAuditHas($service, 'validarContextoFiscalAntesDaReserva', 'service valida contexto fiscal antes de reservar');
nfseAuditHas($service, 'NfseConfigService::prestadorCnpj()', 'service resolve CNPJ pela configuração atual');
nfseAuditHas($service, 'NfseConfigService::ambiente()', 'service resolve ambiente pela configuração atual');
nfseAuditHas($service, 'NfseConfigService::dpsSerie()', 'service resolve série pela configuração atual');

echo "NFS-e reemission audit static checks passed\n";
