<?php

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$arquivo = tempnam(sys_get_temp_dir(), 'embedded_signup_attempt_');
file_put_contents($arquivo, json_encode(['finish' => null, 'used_at' => null]));

$readerCode = <<<'PHP_CHILD'
$arquivo = $argv[1];
$deadline = microtime(true) + 1.5;
do{
    $fh = fopen($arquivo, 'c+');
    flock($fh, LOCK_SH);
    $json = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $attempt = json_decode($json ?: '{}', true);
    if(!empty($attempt['finish'])){
        echo "FINISH\n";
        exit(0);
    }
    usleep(50000);
}while(microtime(true) < $deadline);
echo "TIMEOUT\n";
exit(0);
PHP_CHILD;

$cmd = [PHP_BINARY, '-r', $readerCode, $arquivo];
$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$assert(is_resource($proc), 'processo leitor iniciou');

usleep(150000);
$fh = fopen($arquivo, 'c+');
flock($fh, LOCK_EX);
ftruncate($fh, 0);
rewind($fh);
fwrite($fh, json_encode(['finish' => ['ids' => ['waba_id' => '111']], 'used_at' => null]));
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);

$saida = stream_get_contents($pipes[1]);
$erro = stream_get_contents($pipes[2]);
$status = proc_close($proc);
$assert($status === 0 && trim($saida) === 'FINISH', 'FINISH foi gravado por outro processo enquanto o callback aguardava: ' . $erro);

// Consumo atômico: dois callbacks/processos concorrentes, só um deve marcar used_at.
file_put_contents($arquivo, json_encode(['finish' => ['ids' => ['waba_id' => '111']], 'used_at' => null]));
$consumerCode = <<<'PHP_CHILD'
$arquivo = $argv[1];
$fh = fopen($arquivo, 'c+');
flock($fh, LOCK_EX);
$json = stream_get_contents($fh);
$attempt = json_decode($json ?: '{}', true);
if(!empty($attempt['used_at'])){
    flock($fh, LOCK_UN);
    fclose($fh);
    echo "USED\n";
    exit(0);
}
$attempt['used_at'] = time();
ftruncate($fh, 0);
rewind($fh);
fwrite($fh, json_encode($attempt));
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);
echo "CONSUMED\n";
PHP_CHILD;

$cmd = [PHP_BINARY, '-r', $consumerCode, $arquivo];
$p1 = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1);
$p2 = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2);
$out1 = trim(stream_get_contents($pipes1[1]));
$out2 = trim(stream_get_contents($pipes2[1]));
proc_close($p1);
proc_close($p2);
$resultados = [$out1, $out2];
sort($resultados);
$assert($resultados === ['CONSUMED', 'USED'], 'consumo concorrente permite exatamente um vencedor');

@unlink($arquivo);
echo "Embedded signup concurrent finish tests passed\n";
