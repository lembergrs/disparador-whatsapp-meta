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
