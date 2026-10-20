<?php
$controller = file_get_contents(__DIR__ . '/../app/Controllers/TemplateController.php');
$view = file_get_contents(__DIR__ . '/../app/Views/templates/index.php');
function check($ok, $message){
    if(!$ok){ fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}
check(strpos($controller, 'if(count($contas) === 1)') !== false, 'somente uma conta ativa sincronizacao automatica');
check(strpos($controller, '$this->sincronizarConta($metaId, $usuario);') !== false, 'sincronizacao automatica reutiliza servico');
check(strpos($controller, '$templates =') > strpos($controller, 'if(count($contas) === 1)'), 'lista carregada apos sincronizacao');
check(substr_count($controller, 'private function sincronizarConta(') === 1, 'logica de sincronizacao centralizada');
check(strpos($controller, "catch(\\Throwable $e)") !== false, 'falhas na Meta nao impedem pagina');
check(strpos($view, "count($contas) === 1 ? 'selected'") !== false, 'unica conta preselecionada');
check(strpos($view, 'Sincronizar Templates') !== false, 'botao manual preservado');
echo "Template single-account sync checks passed\n";
