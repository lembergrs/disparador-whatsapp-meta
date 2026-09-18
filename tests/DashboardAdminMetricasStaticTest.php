<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/DashboardController.php');
$view = file_get_contents($root . '/app/Views/dashboard/index.php');
$model = file_get_contents($root . '/app/Models/DashboardAdmin.php');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($controller, "if(\$usuario['nivel'] == 'admin')") !== false, 'Dashboard administrativo deve continuar isolado por nível.');
$assert(strpos($controller, 'new \\Models\\DashboardAdmin') !== false, 'Admin deve carregar o agregador de métricas.');
$assert(strpos($view, 'Funil de integração de clientes') !== false, 'View deve exibir o funil administrativo.');
$assert(strpos($view, 'Evolução de cadastros') !== false, 'View deve exibir evolução de cadastros.');
$assert(strpos($view, 'Cadastros x primeiros pagamentos') !== false, 'View deve comparar cadastros e primeiros pagamentos.');
$assert(strpos($view, 'Últimos clientes cadastrados') !== false, 'View deve exibir clientes recentes.');
$assert(strpos($model, 'CLI_DataCadastro') !== false, 'Cadastros devem usar a data real de cadastro.');
$assert(strpos($model, "MTA_Status='conectado'") !== false, 'Conta Meta conectada deve exigir status conectado.');
$assert(strpos($model, "COB_Status='pago'") !== false && strpos($model, 'COB_DataPagamento') !== false, 'Primeiro pagamento deve usar cobrança paga e data de pagamento.');
$assert(strpos($model, "COB_Tipo='mensalidade'") !== false, 'Conversão deve considerar mensalidade.');
$assert(strpos($model, "ASS_Status='ativa'") !== false, 'Assinaturas ativas devem vir da assinatura.');
$assert(strpos($model, "ASS_Valor/12") !== false, 'MRR deve mensalizar ciclos anuais.');

$adminPos = strpos($view, "<?php if(\$usuario['nivel'] == 'admin'){ ?>");
$clientePos = strpos($view, "<?php }else{ ?>", $adminPos);
$assert($adminPos !== false && $clientePos !== false && $clientePos > $adminPos, 'Dashboard do cliente deve permanecer em ramo separado do admin.');

echo "Dashboard admin checks passed\n";
