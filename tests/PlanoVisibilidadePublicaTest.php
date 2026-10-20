<?php
$model = file_get_contents(__DIR__ . '/../app/Models/Plano.php');
$admin = file_get_contents(__DIR__ . '/../app/Controllers/FinanceiroAdminController.php');
$site = file_get_contents(__DIR__ . '/../app/Controllers/SiteController.php');
function verify($ok, $message){
    if(!$ok){ fwrite(STDERR, "FAIL: " . $message . PHP_EOL); exit(1); }
}
verify(strpos($site, 'listarPublicosAtivos()') !== false, 'site deve listar apenas planos publicos');
verify(substr_count($model, "AND PLA_Publico = 'S'") === 2, 'listagem e busca publica devem filtrar visibilidade');
verify(strpos($model, "migration PLA_Publico pendente") !== false, 'sem coluna nao deve expor planos ativos');
$salvar = substr($admin, strpos($admin, 'public function salvarPlano()'), strpos($admin, 'public function editarPlano()') - strpos($admin, 'public function salvarPlano()'));
$editar = substr($admin, strpos($admin, 'public function editarPlano()'), strpos($admin, 'public function inativarPlano()') - strpos($admin, 'public function editarPlano()'));
verify(strpos($salvar, "'publico' => !empty(\$_POST['publico']) ? 'S' : 'N'") !== false, 'cadastro salva visibilidade');
verify(strpos($editar, "'publico' => !empty(\$_POST['publico']) ? 'S' : 'N'") !== false, 'edicao salva visibilidade');
echo "Plano visibility checks passed" . PHP_EOL;
