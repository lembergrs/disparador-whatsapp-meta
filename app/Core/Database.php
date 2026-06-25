<?php

namespace Core;

use PDO;
use PDOException;

class Database
{
    private static $instance;

    public static function getInstance()
    {
        if(!self::$instance){

            try{

                self::$instance = new PDO(
                    "mysql:host=".DB_HOST.";
                    dbname=".DB_NAME.";
                    charset=utf8mb4",
                    DB_USER,
                    DB_PASS
                );

                self::$instance->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

                self::registrarConexaoCriada();

            }catch(PDOException $e){

                self::registrarErroConexao($e);

                $ambiente = defined('APP_ENV') ? APP_ENV : (function_exists('app_env') ? app_env() : 'production');
                $local = in_array($ambiente, ['local', 'dev', 'development'], true);

                if($local){
                    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
                }

                http_response_code(500);
                die('Erro interno ao conectar ao banco de dados. Tente novamente mais tarde.');

            }

        }

        return self::$instance;
    }



    private static function registrarConexaoCriada()
    {
        if(!self::debugConexaoAtivo()){
            return;
        }

        $diretorioLog = dirname(__DIR__, 2) . '/storage/logs';

        if(!is_dir($diretorioLog)){
            mkdir($diretorioLog, 0770, true);
        }

        $origem = PHP_SAPI === 'cli' ? 'cli' : 'web';
        $dados = [
            'data_hora' => date('Y-m-d H:i:s'),
            'origem' => $origem,
            'request_uri' => $origem === 'web' ? self::sanitizarTexto($_SERVER['REQUEST_URI'] ?? '') : null,
            'script_name' => self::sanitizarTexto($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '')),
            'metodo_http' => $origem === 'web' ? self::sanitizarTexto($_SERVER['REQUEST_METHOD'] ?? '') : null,
            'ip' => self::sanitizarIp($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => self::sanitizarTexto($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'backtrace' => self::backtraceResumido()
        ];

        error_log(
            json_encode($dados, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            3,
            $diretorioLog . '/database-connections.log'
        );
    }

    private static function debugConexaoAtivo()
    {
        $debug = getenv('DB_CONNECTION_DEBUG');

        if(is_string($debug) && in_array(strtolower($debug), ['1', 'true', 'yes', 'on'], true)){
            return true;
        }

        $ambiente = defined('APP_ENV') ? APP_ENV : (function_exists('app_env') ? app_env() : 'production');

        return in_array($ambiente, ['local', 'dev', 'development'], true);
    }

    private static function backtraceResumido()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        $resumo = [];

        foreach($trace as $frame){
            $arquivo = $frame['file'] ?? '';
            $linha = $frame['line'] ?? '';
            $classe = $frame['class'] ?? '';
            $funcao = $frame['function'] ?? '';

            if($funcao === 'backtraceResumido' || $funcao === 'registrarConexaoCriada'){
                continue;
            }

            $resumo[] = trim($classe . ($classe !== '' ? '::' : '') . $funcao . ' ' . basename($arquivo) . ':' . $linha);
        }

        return array_slice($resumo, 0, 5);
    }

    private static function sanitizarTexto($valor)
    {
        $valor = preg_replace('/(access_token|token|senha|password|secret|key)=([^&]+)/i', '$1=***', (string) $valor);
        $valor = preg_replace('/[\r\n\t]+/', ' ', $valor);

        return mb_substr($valor, 0, 500);
    }

    private static function sanitizarIp($ip)
    {
        return self::sanitizarTexto($ip);
    }

    private static function registrarErroConexao(PDOException $e)
    {
        $diretorioLog = dirname(__DIR__, 2) . '/storage/logs';

        if(!is_dir($diretorioLog)){
            mkdir($diretorioLog, 0770, true);
        }

        $linha = sprintf(
            "[%s] Erro de conexão com o banco: %s em %s:%s%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL
        );

        error_log($linha, 3, $diretorioLog . '/database-error.log');
    }
}
