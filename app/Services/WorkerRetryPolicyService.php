<?php

namespace Services;

class WorkerRetryPolicyService
{
    public const ERRO_TEMPORARIO = 'erro_temporario';
    public const ERRO_DEFINITIVO = 'erro_definitivo';
    public const BLOQUEIO_TEMPORARIO = 'bloqueio_temporario';
    public const BLOQUEIO_DEFINITIVO = 'bloqueio_definitivo';
    public const ERRO_PERSISTENCIA_POS_ENVIO = 'erro_persistencia_pos_envio';

    public function maxTentativas(): int
    {
        return max(1, (int) (defined('WORKER_MAX_ATTEMPTS') ? WORKER_MAX_ATTEMPTS : 5));
    }

    public function calcularDelay(int $tentativas): int
    {
        $base = max(1, (int) (defined('WORKER_RETRY_DELAY_SECONDS') ? WORKER_RETRY_DELAY_SECONDS : 30));
        $maximo = max($base, (int) (defined('WORKER_RETRY_MAX_DELAY_SECONDS') ? WORKER_RETRY_MAX_DELAY_SECONDS : 1800));
        $jitterMaximo = max(0, (int) (defined('WORKER_RETRY_JITTER_SECONDS') ? WORKER_RETRY_JITTER_SECONDS : 15));
        $expoente = max(0, $tentativas - 1);
        $delay = min($base * (2 ** $expoente), $maximo);

        if($jitterMaximo > 0){
            $delay += mt_rand(0, $jitterMaximo);
        }

        return (int) min($delay, $maximo + $jitterMaximo);
    }

    public function proximaTentativaSql(int $tentativas): string
    {
        return 'DATE_ADD(NOW(), INTERVAL ' . $this->calcularDelay($tentativas) . ' SECOND)';
    }

    public function classificarRetorno($retorno): array
    {
        if(isset($retorno['messages'][0]['id'])){
            return [
                'sucesso' => true,
                'message_id' => $retorno['messages'][0]['id'],
                'tipo_resultado' => 'aceito_meta',
                'retry' => false,
                'erro_codigo' => null,
                'erro_mensagem' => null
            ];
        }

        $httpCode = is_array($retorno) ? (int) ($retorno['http_code'] ?? 0) : 0;
        $erro = is_array($retorno) ? ($retorno['error'] ?? []) : [];
        $codigoErro = is_array($erro) ? (string) ($erro['code'] ?? '') : '';
        $mensagem = is_array($erro) ? (string) ($erro['message'] ?? '') : '';
        $curlError = is_array($retorno) ? (string) ($retorno['curl_error'] ?? '') : '';

        $temporario = $this->ehErroTemporario($httpCode, $codigoErro, $mensagem, $curlError);

        return [
            'sucesso' => false,
            'message_id' => null,
            'tipo_resultado' => $temporario ? self::ERRO_TEMPORARIO : self::ERRO_DEFINITIVO,
            'retry' => $temporario,
            'erro_codigo' => $codigoErro !== '' ? $codigoErro : ($httpCode > 0 ? (string) $httpCode : null),
            'erro_mensagem' => $this->extrairMensagemErro($retorno, $temporario)
        ];
    }

    public function classificarBloqueio(array $validacao): string
    {
        return ($validacao['status'] ?? '') === self::BLOQUEIO_TEMPORARIO
            ? self::BLOQUEIO_TEMPORARIO
            : self::BLOQUEIO_DEFINITIVO;
    }

    public function atingiuMaximo(int $tentativas): bool
    {
        return $tentativas >= $this->maxTentativas();
    }

    private function ehErroTemporario(int $httpCode, string $codigoErro, string $mensagem, string $curlError): bool
    {
        $mensagemNormalizada = strtolower($mensagem . ' ' . $curlError);
        $codigo = (int) $codigoErro;

        return $httpCode === 0
            || $httpCode === 408
            || $httpCode === 409
            || $httpCode === 425
            || $httpCode === 429
            || $httpCode >= 500
            || in_array($codigo, [4, 17, 32, 613], true)
            || strpos($mensagemNormalizada, 'timeout') !== false
            || strpos($mensagemNormalizada, 'timed out') !== false
            || strpos($mensagemNormalizada, 'tempor') !== false
            || strpos($mensagemNormalizada, 'rate limit') !== false
            || strpos($mensagemNormalizada, 'too many') !== false
            || strpos($mensagemNormalizada, 'could not resolve') !== false
            || strpos($mensagemNormalizada, 'connection') !== false;
    }

    private function extrairMensagemErro($retorno, bool $temporario): string
    {
        if(is_array($retorno) && !empty($retorno['error']['message'])){
            return $retorno['error']['message'];
        }

        if(is_array($retorno) && !empty($retorno['curl_error'])){
            return $retorno['curl_error'];
        }

        if($temporario){
            return 'Falha temporária ao enviar mensagem.';
        }

        return is_array($retorno)
            ? json_encode($retorno, JSON_UNESCAPED_UNICODE)
            : 'Erro ao enviar mensagem';
    }
}
