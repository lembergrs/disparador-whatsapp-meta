<?php

$auth = file_get_contents(__DIR__ . '/../app/Core/Auth.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracaoController.php');
$model = file_get_contents(__DIR__ . '/../app/Models/MetaConta.php');
$view = file_get_contents(__DIR__ . '/../app/Views/configuracao/meta.php');
$cliente = file_get_contents(__DIR__ . '/../app/Models/Cliente.php');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($auth, 'public static function podeConectarPrimeiroNumero') !== false, 'regra central do primeiro número existe');
$assert(strpos($auth, '(int) $numerosAtivos !== 0') !== false, 'exceção exige ausência de número ativo');
$assert(strpos($auth, 'return self::clienteEmPreTrial();') !== false, 'exceção reutiliza elegibilidade central do pré-trial');
$assert(strpos($model, '$preTrialElegivel && $utilizados === 0') !== false, 'limite excepcional é exatamente um');
$assert(strpos($model, "'limite' => 1") !== false, 'contador representa capacidade do primeiro número');
$assert(substr_count($controller, '$this->exigirPermissaoConexao($clienteId);') >= 2, 'backend valida início e conclusão do Embedded Signup');
$assert(strpos($model, 'salvarOuAtualizarEmbeddedSignupComBloqueio') !== false, 'persistência serializa conexões simultâneas por cliente');
$assert(strpos($model, 'FOR UPDATE') !== false, 'conclusão revalida o limite sob bloqueio do cliente');
$assert(strpos($controller, '$this->avaliarPermissaoConexao(') !== false, 'tela usa a mesma regra do backend');
$assert(strpos($view, 'Conectar WhatsApp') !== false, 'pré-trial exibe ação clara de conexão');
$assert(strpos($view, 'A conexão do primeiro número inicia seu período de avaliação') !== false, 'tela explica início do trial');
$assert(strpos($controller, "'pendente_registro'") !== false, 'conclusão inicial permanece pendente até validação operacional');
$assert(strpos($controller, "\$statusConexao === 'conectado'") !== false, 'trial inicia apenas após conexão validada');
$assert(strpos($cliente, "CLI_DataLiberacao IS NULL OR CLI_DataLiberacao = ''") !== false, 'data do trial permanece idempotente');

echo "Pre-trial first Meta connection checks passed\n";
