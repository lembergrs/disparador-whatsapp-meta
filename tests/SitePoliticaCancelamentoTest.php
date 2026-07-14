<?php

$root = dirname(__DIR__);

function assertContainsText($haystack, $needle, $message)
{
    if(strpos($haystack, $needle) === false){
        throw new Exception($message);
    }
}

function assertFileExistsForPolicy($path, $message)
{
    if(!is_file($path)){
        throw new Exception($message);
    }
}

$controller = file_get_contents($root . '/app/Controllers/SiteController.php');
$cadastro = file_get_contents($root . '/app/Views/site/cadastro.php');
$termos = file_get_contents($root . '/app/Views/site/termos_uso.php');
$privacidade = file_get_contents($root . '/app/Views/site/politica_privacidade.php');
$home = file_get_contents($root . '/app/Views/site/home.php');

assertContainsText(
    $controller,
    'public function politicaCancelamento()',
    'SiteController deve expor o método politicaCancelamento.'
);

assertContainsText(
    $controller,
    "'site/politica_cancelamento'",
    'politicaCancelamento deve renderizar a view correta.'
);

assertFileExistsForPolicy(
    $root . '/app/Views/site/politica_cancelamento.php',
    'A view pública da política de cancelamento deve existir.'
);

assertContainsText(
    $cadastro,
    'site/politicaCancelamento',
    'Cadastro deve conter link para a Política de Cancelamento e Reembolso.'
);

assertContainsText(
    $cadastro,
    'target="_blank"',
    'Links de documentos no cadastro devem abrir em nova aba.'
);

assertContainsText(
    $cadastro,
    'Política de Cancelamento e Reembolso',
    'Checkbox deve citar a Política de Cancelamento e Reembolso.'
);

assertContainsText(
    $controller,
    "empty(\$_POST['aceiteTermos'])",
    'Backend deve validar aceiteTermos antes de concluir o cadastro.'
);

assertContainsText(
    $controller,
    'Você precisa aceitar os Termos de Uso, a Política de Privacidade e a Política de Cancelamento e Reembolso.',
    'Validação backend de aceite deve informar os documentos aceitos.'
);

assertContainsText(
    $termos,
    'Cancelamento, Reembolso e Período de Avaliação',
    'Termos devem conter seção resumida de cancelamento e reembolso.'
);

assertContainsText(
    $termos,
    'site/politicaCancelamento',
    'Termos devem referenciar a página detalhada da política.'
);

assertContainsText(
    $controller,
    'public function termosUso()',
    'Rota de Termos de Uso não deve regredir.'
);

assertContainsText(
    $controller,
    'public function politicaPrivacidade()',
    'Rota de Política de Privacidade não deve regredir.'
);

assertContainsText(
    $privacidade,
    'Política de Privacidade',
    'View de Política de Privacidade deve continuar existindo.'
);

assertContainsText(
    $home,
    'site/politicaCancelamento',
    'Home deve incluir link institucional para a Política de Cancelamento e Reembolso.'
);

echo "Política de cancelamento pública OK\n";
