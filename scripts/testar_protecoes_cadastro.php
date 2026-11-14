<?php
/**
 * Teste seguro do cadastro público: php scripts/testar_protecoes_cadastro.php
 * Não envia HTTP, não usa o reCAPTCHA real e não acessa o banco de dados.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Somente CLI.\n");
}

$raiz = dirname(__DIR__);
require_once $raiz . '/vendor/autoload.php';

$arquivoController = $raiz . '/app/Controllers/SiteController.php';
$codigo = file_get_contents($arquivoController);
if ($codigo === false) {
    fwrite(STDERR, "ERRO: não foi possível ler SiteController.php\n");
    exit(1);
}

$falhas = 0;
$verificar = static function (bool $condicao, string $descricao) use (&$falhas): void {
    echo ($condicao ? '[OK] ' : '[FALHA] ') . $descricao . PHP_EOL;
    if (!$condicao) {
        ++$falhas;
    }
};

// Verificações estáticas: NÃO substituem um teste de integração HTTP.
$verificar(str_contains($codigo, 'if (!$telefoneValido)'), 'Validação de telefone presente no fluxo de cadastro');
$verificar(str_contains($codigo, "preg_match('/^(.)\\\\1+$/', \$telefoneNacional)"), 'Bloqueio de números repetidos presente');
$verificar(str_contains($codigo, "RECAPTCHA_SECRET_KEY") && str_contains($codigo, "RECAPTCHA_SITE_KEY"), 'Chaves de reCAPTCHA verificadas no fluxo');
$verificar(str_contains($codigo, "https://www.google.com/recaptcha/api/siteverify"), 'Validação de reCAPTCHA enviada ao Google');
$verificar(str_contains($codigo, "['success'] ?? false") && str_contains($codigo, 'hash_equals('), 'Resposta e hostname do reCAPTCHA conferidos');

// Teste real da função de rate limit, isolado por IP reservado para documentação.
// O arquivo de teste é removido ao final, sem tocar em dados de clientes.
$ip = '192.0.2.' . random_int(1, 254);
$arquivo = $raiz . '/storage/cadastro_rate_limit/' . hash('sha256', $ip) . '.json';
if (file_exists($arquivo)) {
    fwrite(STDERR, "ERRO: arquivo de teste já existe; não será alterado.\n");
    exit(1);
}

$ipAnterior = $_SERVER['REMOTE_ADDR'] ?? null;
$_SERVER['REMOTE_ADDR'] = $ip;
try {
    $controller = new \Controllers\SiteController();
    $metodo = new ReflectionMethod($controller, 'permitirTentativaCadastroPorIp');
    $resultados = [];
    for ($i = 0; $i < 6; ++$i) {
        $resultados[] = $metodo->invoke($controller);
    }
    $verificar($resultados === [true, true, true, true, true, false], 'Rate limit: cinco tentativas permitidas, sexta bloqueada');

    // Simula registros antigos (fora da última hora, mas dentro de 24h).
    $registros = array_fill(0, 11, time() - 7200);
    file_put_contents($arquivo, json_encode($registros), LOCK_EX);
    $verificar($metodo->invoke($controller) === true, 'Rate limit: 12ª tentativa em 24 horas permitida');
    $verificar($metodo->invoke($controller) === false, 'Rate limit: 13ª tentativa em 24 horas bloqueada');
} catch (Throwable $e) {
    ++$falhas;
    fwrite(STDERR, '[FALHA] Exceção: ' . $e->getMessage() . PHP_EOL);
} finally {
    if ($ipAnterior === null) {
        unset($_SERVER['REMOTE_ADDR']);
    } else {
        $_SERVER['REMOTE_ADDR'] = $ipAnterior;
    }
    if (is_file($arquivo)) {
        unlink($arquivo);
    }
}

echo $falhas === 0 ? "TESTES CONCLUÍDOS SEM FALHAS\n" : "FALHAS: $falhas\n";
exit($falhas === 0 ? 0 : 1);
