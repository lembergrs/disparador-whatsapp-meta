<?php

if(!function_exists('projetoRaiz')){

    function projetoRaiz()
    {
        return dirname(__DIR__);
    }
}

if(!function_exists('caminhoEnvProjeto')){

    function caminhoEnvProjeto()
    {
        return projetoRaiz() . '/.env';
    }
}

if(!function_exists('diretorioLogsProjeto')){

    function diretorioLogsProjeto()
    {
        return projetoRaiz() . '/storage/logs';
    }
}

if(!function_exists('ambienteLocal')){

    function ambienteLocal($ambiente)
    {
        return in_array($ambiente, ['local', 'dev', 'development'], true);
    }
}

if(!function_exists('registrarErroEnv')){

    function registrarErroEnv($mensagem)
    {
        $diretorioLog = diretorioLogsProjeto();

        if(!is_dir($diretorioLog)){
            mkdir($diretorioLog, 0770, true);
        }

        error_log(
            '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL,
            3,
            $diretorioLog . '/env-error.log'
        );
    }
}

if(!function_exists('falharConfiguracaoEnv')){

    function falharConfiguracaoEnv($mensagem)
    {
        registrarErroEnv($mensagem);

        $ambiente = app_env();

        if(ambienteLocal($ambiente)){
            http_response_code(500);
            exit($mensagem);
        }

        http_response_code(500);
        exit('Erro interno de configuração. Verifique os logs da aplicação.');
    }
}

if(!function_exists('carregarEnvLocal')){

    function carregarEnvLocal($caminho = null)
    {
        $caminho = $caminho ?: caminhoEnvProjeto();

        if(!is_file($caminho) || !is_readable($caminho)){
            $GLOBALS['ENV_ARQUIVO_CARREGADO'] = false;
            $GLOBALS['ENV_ARQUIVO_CAMINHO'] = $caminho;
            return;
        }

        $GLOBALS['ENV_ARQUIVO_CARREGADO'] = true;
        $GLOBALS['ENV_ARQUIVO_CAMINHO'] = $caminho;

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

if(!function_exists('validarConfiguracaoEnv')){

    function validarConfiguracaoEnv()
    {
        $caminhoEnv = $GLOBALS['ENV_ARQUIVO_CAMINHO'] ?? caminhoEnvProjeto();

        if(empty($GLOBALS['ENV_ARQUIVO_CARREGADO']) && !is_file($caminhoEnv)){
            falharConfiguracaoEnv(
                'Arquivo .env não encontrado na raiz do projeto: ' . $caminhoEnv
            );
        }

        $obrigatorias = ['DB_HOST', 'DB_NAME', 'DB_USER'];
        $ausentes = [];

        foreach($obrigatorias as $chave){
            $valor = getenv($chave);

            if($valor === false || trim((string) $valor) === ''){
                $ausentes[] = $chave;
            }
        }

        if(!empty($ausentes)){
            falharConfiguracaoEnv(
                'Variáveis obrigatórias ausentes no ambiente/.env: ' . implode(', ', $ausentes)
            );
        }
    }
}

if(!function_exists('configurarErrosAplicacao')){

    function configurarErrosAplicacao()
    {
        $ambiente = app_env();
        $local = ambienteLocal($ambiente);

        ini_set('display_errors', $local ? '1' : '0');
        ini_set('display_startup_errors', $local ? '1' : '0');
        ini_set('log_errors', '1');

        if(!defined('STDERR')){
            $diretorioLog = diretorioLogsProjeto();

            if(!is_dir($diretorioLog)){
                mkdir($diretorioLog, 0775, true);
            }

            ini_set('error_log', $diretorioLog . '/php-error.log');
        }

        error_reporting(E_ALL);
    }
}

carregarEnvLocal();
validarConfiguracaoEnv();
