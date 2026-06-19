<?php

if(!function_exists('carregarEnvLocal')){

    function carregarEnvLocal($caminho = null)
    {
        $caminho = $caminho ?: dirname(__DIR__) . '/.env';

        if(!is_file($caminho) || !is_readable($caminho)){
            return;
        }

        $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach($linhas as $linha){
            $linha = trim($linha);

            if($linha === '' || strpos($linha, '#') === 0 || strpos($linha, '=') === false){
                continue;
            }

            [$chave, $valor] = explode('=', $linha, 2);

            $chave = trim($chave);
            $valor = trim($valor);

            if($chave === ''){
                continue;
            }

            if(
                strlen($valor) >= 2
                && (
                    ($valor[0] === '"' && substr($valor, -1) === '"')
                    ||
                    ($valor[0] === "'" && substr($valor, -1) === "'")
                )
            ){
                $valor = substr($valor, 1, -1);
            }

            if(getenv($chave) !== false){
                continue;
            }

            putenv($chave . '=' . $valor);
            $_ENV[$chave] = $valor;
            $_SERVER[$chave] = $valor;
        }
    }
}

if(!function_exists('env_valor')){

    function env_valor($chave, $padrao = null)
    {
        $valor = getenv($chave);

        if($valor === false){
            return $padrao;
        }

        return $valor;
    }
}

if(!function_exists('app_env')){

    function app_env()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return env_valor(
            'APP_ENV',
            in_array($host, ['localhost', '127.0.0.1', 'disparador.test'], true)
                ? 'local'
                : 'production'
        );
    }
}

if(!function_exists('configurarErrosAplicacao')){

    function configurarErrosAplicacao()
    {
        $ambiente = app_env();
        $local = in_array($ambiente, ['local', 'dev', 'development'], true);

        ini_set('display_errors', $local ? '1' : '0');
        ini_set('display_startup_errors', $local ? '1' : '0');
        ini_set('log_errors', '1');

        if(!defined('STDERR')){
            $diretorioLog = dirname(__DIR__) . '/storage/logs';

            if(!is_dir($diretorioLog)){
                mkdir($diretorioLog, 0775, true);
            }

            ini_set('error_log', $diretorioLog . '/php-error.log');
        }

        error_reporting(E_ALL);
    }
}

carregarEnvLocal();
