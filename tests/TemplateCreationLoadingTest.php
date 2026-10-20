<?php
$view = file_get_contents(__DIR__ . '/../app/Views/templates/index.php');
$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($view, 'id="btnCriarTemplate"') !== false, 'botao de criar template deve ter identificador');
$assert(strpos($view, 'id="statusCriacaoTemplate"') !== false, 'mensagem de progresso deve existir');
$assert(strpos($view, 'fa-spinner fa-spin') !== false, 'spinner deve existir');
$assert(strpos($view, "if(formulario.data('enviando'))") !== false, 'envio duplicado deve ser bloqueado');
$assert(strpos($view, "formulario.data('enviando', true)") !== false, 'envio deve ser marcado em andamento');
$assert(strpos($view, 'atualizarEstadoCriacaoTemplate(true);') !== false, 'botao deve indicar processamento');
$assert(strpos($view, 'atualizarEstadoCriacaoTemplate(false);') !== false, 'botao deve ser restaurado no retorno do historico');
$assert(strpos($view, "if(totalTelefone > 1)") < strpos($view, "formulario.data('enviando', true)"), 'bloqueio deve ocorrer apos validacoes');
echo "Template creation loading checks passed\n";
